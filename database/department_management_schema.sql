-- ============================================================================
-- FlexPBX Department and Team Management System
-- Database Schema
-- Created: 2025-10-17
-- ============================================================================

-- Drop existing tables if they exist (for clean installation)
DROP TABLE IF EXISTS `team_member_skills`;
DROP TABLE IF EXISTS `team_members`;
DROP TABLE IF EXISTS `team_schedules`;
DROP TABLE IF EXISTS `teams`;
DROP TABLE IF EXISTS `department_queues`;
DROP TABLE IF EXISTS `department_managers`;
DROP TABLE IF EXISTS `department_settings`;
DROP TABLE IF EXISTS `departments`;

-- ============================================================================
-- Table: departments
-- Main department table with hierarchical support
-- ============================================================================
CREATE TABLE `departments` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `parent_id` INT(11) UNSIGNED DEFAULT NULL,
  `manager_type` ENUM('single', 'team') DEFAULT 'single' COMMENT 'Single person or team management',
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` VARCHAR(50),
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `status` (`status`),
  FOREIGN KEY (`parent_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Department organizational structure';

-- ============================================================================
-- Table: department_managers
-- Associates managers (users) with departments
-- Supports multiple managers per department
-- ============================================================================
CREATE TABLE `department_managers` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `department_id` INT(11) UNSIGNED NOT NULL,
  `user_id` INT(11) UNSIGNED DEFAULT NULL,
  `username` VARCHAR(50) NOT NULL COMMENT 'Username from admin system',
  `extension` VARCHAR(20) DEFAULT NULL,
  `role` ENUM('manager', 'assistant_manager', 'supervisor', 'team_lead') DEFAULT 'manager',
  `permissions` JSON COMMENT 'Specific permissions for this manager',
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `assigned_by` VARCHAR(50),
  `is_primary` TINYINT(1) DEFAULT 0 COMMENT 'Primary manager for department',
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  KEY `username` (`username`),
  KEY `extension` (`extension`),
  UNIQUE KEY `unique_dept_user` (`department_id`, `username`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Department manager assignments';

-- ============================================================================
-- Table: department_settings
-- Stores department-specific configuration
-- ============================================================================
CREATE TABLE `department_settings` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `department_id` INT(11) UNSIGNED NOT NULL,
  `business_hours_start` TIME DEFAULT '09:00:00',
  `business_hours_end` TIME DEFAULT '17:00:00',
  `timezone` VARCHAR(50) DEFAULT 'America/New_York',
  `working_days` JSON COMMENT 'Array of working days: [1,2,3,4,5]',
  `overflow_action` ENUM('voicemail', 'queue', 'forward', 'hangup') DEFAULT 'voicemail',
  `overflow_destination` VARCHAR(100) DEFAULT NULL,
  `voicemail_enabled` TINYINT(1) DEFAULT 1,
  `voicemail_email` VARCHAR(255) DEFAULT NULL,
  `max_queue_time` INT(11) DEFAULT 300 COMMENT 'Max queue time in seconds',
  `auto_answer` TINYINT(1) DEFAULT 0,
  `recording_enabled` TINYINT(1) DEFAULT 0,
  `settings_json` JSON COMMENT 'Additional custom settings',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_dept_settings` (`department_id`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Department operational settings';

-- ============================================================================
-- Table: department_queues
-- Links departments to Asterisk call queues
-- ============================================================================
CREATE TABLE `department_queues` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `department_id` INT(11) UNSIGNED NOT NULL,
  `queue_name` VARCHAR(80) NOT NULL COMMENT 'Asterisk queue name',
  `queue_extension` VARCHAR(20) DEFAULT NULL,
  `priority` INT(11) DEFAULT 1,
  `strategy` ENUM('ringall', 'leastrecent', 'fewestcalls', 'random', 'rrmemory', 'linear', 'wrandom') DEFAULT 'ringall',
  `timeout` INT(11) DEFAULT 30,
  `retry` INT(11) DEFAULT 5,
  `maxlen` INT(11) DEFAULT 0 COMMENT '0 = unlimited',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  KEY `queue_name` (`queue_name`),
  UNIQUE KEY `unique_dept_queue` (`department_id`, `queue_name`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Department call queue assignments';

-- ============================================================================
-- Table: teams
-- Teams within departments for better organization
-- ============================================================================
CREATE TABLE `teams` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `department_id` INT(11) UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `team_lead_id` INT(11) UNSIGNED DEFAULT NULL,
  `team_lead_username` VARCHAR(50) DEFAULT NULL,
  `team_lead_extension` VARCHAR(20) DEFAULT NULL,
  `team_type` ENUM('sales', 'support', 'technical', 'billing', 'custom') DEFAULT 'custom',
  `max_members` INT(11) DEFAULT 0 COMMENT '0 = unlimited',
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` VARCHAR(50),
  PRIMARY KEY (`id`),
  KEY `department_id` (`department_id`),
  KEY `team_lead_username` (`team_lead_username`),
  KEY `status` (`status`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Teams within departments';

-- ============================================================================
-- Table: team_members
-- Team membership and individual member details
-- ============================================================================
CREATE TABLE `team_members` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `team_id` INT(11) UNSIGNED NOT NULL,
  `user_id` INT(11) UNSIGNED DEFAULT NULL,
  `username` VARCHAR(50) NOT NULL,
  `extension` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `role` ENUM('member', 'senior', 'lead', 'assistant_lead') DEFAULT 'member',
  `skill_level` ENUM('junior', 'intermediate', 'senior', 'expert') DEFAULT 'intermediate',
  `hire_date` DATE DEFAULT NULL,
  `status` ENUM('active', 'inactive', 'on_leave', 'training') DEFAULT 'active',
  `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `added_by` VARCHAR(50),
  PRIMARY KEY (`id`),
  KEY `team_id` (`team_id`),
  KEY `username` (`username`),
  KEY `extension` (`extension`),
  KEY `status` (`status`),
  UNIQUE KEY `unique_team_member` (`team_id`, `username`),
  FOREIGN KEY (`team_id`) REFERENCES `teams`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Team member assignments';

-- ============================================================================
-- Table: team_member_skills
-- Tracks skills and capabilities of team members
-- ============================================================================
CREATE TABLE `team_member_skills` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `team_member_id` INT(11) UNSIGNED NOT NULL,
  `skill_name` VARCHAR(100) NOT NULL,
  `skill_category` VARCHAR(50) DEFAULT 'general',
  `proficiency` ENUM('beginner', 'intermediate', 'advanced', 'expert') DEFAULT 'intermediate',
  `certified` TINYINT(1) DEFAULT 0,
  `certification_date` DATE DEFAULT NULL,
  `notes` TEXT,
  `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `team_member_id` (`team_member_id`),
  KEY `skill_name` (`skill_name`),
  FOREIGN KEY (`team_member_id`) REFERENCES `team_members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Team member skills tracking';

-- ============================================================================
-- Table: team_schedules
-- Team member schedules and shifts
-- ============================================================================
CREATE TABLE `team_schedules` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `team_member_id` INT(11) UNSIGNED NOT NULL,
  `day_of_week` TINYINT(1) NOT NULL COMMENT '1=Monday, 7=Sunday',
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `break_duration` INT(11) DEFAULT 60 COMMENT 'Break time in minutes',
  `is_working_day` TINYINT(1) DEFAULT 1,
  `effective_from` DATE DEFAULT NULL,
  `effective_to` DATE DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `team_member_id` (`team_member_id`),
  KEY `day_of_week` (`day_of_week`),
  FOREIGN KEY (`team_member_id`) REFERENCES `team_members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Team member work schedules';

-- ============================================================================
-- Sample Data Insertion
-- ============================================================================

-- Insert sample departments
INSERT INTO `departments` (`name`, `description`, `parent_id`, `manager_type`, `status`, `created_by`) VALUES
('Sales', 'Sales department', NULL, 'team', 'active', 'admin'),
('Support', 'Customer support department', NULL, 'team', 'active', 'admin'),
('Technical', 'Technical operations', NULL, 'single', 'active', 'admin'),
('Billing', 'Billing and accounts', NULL, 'single', 'active', 'admin');

-- Insert sub-departments
INSERT INTO `departments` (`name`, `description`, `parent_id`, `manager_type`, `status`, `created_by`) VALUES
('Inbound Sales', 'Handles inbound sales calls', 1, 'single', 'active', 'admin'),
('Outbound Sales', 'Handles outbound sales campaigns', 1, 'team', 'active', 'admin'),
('Level 1 Support', 'First-line customer support', 2, 'team', 'active', 'admin'),
('Level 2 Support', 'Advanced technical support', 2, 'team', 'active', 'admin');

-- Insert default department settings
INSERT INTO `department_settings` (`department_id`, `business_hours_start`, `business_hours_end`, `timezone`, `working_days`, `overflow_action`, `voicemail_enabled`, `max_queue_time`) VALUES
(1, '09:00:00', '18:00:00', 'America/New_York', '[1,2,3,4,5]', 'voicemail', 1, 300),
(2, '08:00:00', '20:00:00', 'America/New_York', '[1,2,3,4,5,6]', 'queue', 1, 600),
(3, '09:00:00', '17:00:00', 'America/New_York', '[1,2,3,4,5]', 'voicemail', 1, 180),
(4, '09:00:00', '17:00:00', 'America/New_York', '[1,2,3,4,5]', 'voicemail', 1, 300);

-- Insert sample teams
INSERT INTO `teams` (`department_id`, `name`, `description`, `team_lead_username`, `team_type`, `status`, `created_by`) VALUES
(1, 'Sales Team Alpha', 'Primary sales team', 'admin', 'sales', 'active', 'admin'),
(2, 'Support Team 1', 'First support team', 'admin', 'support', 'active', 'admin'),
(3, 'Tech Ops Team', 'Technical operations team', 'admin', 'technical', 'active', 'admin');

-- ============================================================================
-- Indexes for Performance
-- ============================================================================

-- Additional indexes for common queries
CREATE INDEX `idx_dept_status` ON `departments` (`status`, `parent_id`);
CREATE INDEX `idx_team_dept_status` ON `teams` (`department_id`, `status`);
CREATE INDEX `idx_member_status` ON `team_members` (`team_id`, `status`);
CREATE INDEX `idx_manager_dept_status` ON `department_managers` (`department_id`, `status`);

-- ============================================================================
-- Views for Common Queries
-- ============================================================================

-- View: Department hierarchy with manager information
CREATE OR REPLACE VIEW `v_department_hierarchy` AS
SELECT
    d.id,
    d.name,
    d.description,
    d.parent_id,
    p.name AS parent_name,
    d.manager_type,
    d.status,
    COUNT(DISTINCT dm.id) AS manager_count,
    COUNT(DISTINCT t.id) AS team_count,
    GROUP_CONCAT(DISTINCT dm.username SEPARATOR ', ') AS managers
FROM departments d
LEFT JOIN departments p ON d.parent_id = p.id
LEFT JOIN department_managers dm ON d.id = dm.department_id AND dm.status = 'active'
LEFT JOIN teams t ON d.id = t.department_id AND t.status = 'active'
GROUP BY d.id, d.name, d.description, d.parent_id, p.name, d.manager_type, d.status;

-- View: Team member summary
CREATE OR REPLACE VIEW `v_team_members_summary` AS
SELECT
    t.id AS team_id,
    t.name AS team_name,
    t.department_id,
    d.name AS department_name,
    t.team_lead_username,
    COUNT(tm.id) AS member_count,
    SUM(CASE WHEN tm.status = 'active' THEN 1 ELSE 0 END) AS active_members,
    SUM(CASE WHEN tm.status = 'inactive' THEN 1 ELSE 0 END) AS inactive_members,
    SUM(CASE WHEN tm.status = 'on_leave' THEN 1 ELSE 0 END) AS on_leave_members
FROM teams t
LEFT JOIN departments d ON t.department_id = d.id
LEFT JOIN team_members tm ON t.id = tm.team_id
GROUP BY t.id, t.name, t.department_id, d.name, t.team_lead_username;

-- View: Department manager dashboard
CREATE OR REPLACE VIEW `v_manager_departments` AS
SELECT
    dm.username AS manager_username,
    dm.extension AS manager_extension,
    dm.role AS manager_role,
    d.id AS department_id,
    d.name AS department_name,
    d.description AS department_description,
    d.status AS department_status,
    dm.is_primary,
    COUNT(DISTINCT t.id) AS team_count,
    COUNT(DISTINCT tm.id) AS total_team_members
FROM department_managers dm
JOIN departments d ON dm.department_id = d.id
LEFT JOIN teams t ON d.id = t.department_id AND t.status = 'active'
LEFT JOIN team_members tm ON t.id = tm.team_id AND tm.status = 'active'
WHERE dm.status = 'active'
GROUP BY dm.username, dm.extension, dm.role, d.id, d.name, d.description, d.status, dm.is_primary;

-- ============================================================================
-- Stored Procedures
-- ============================================================================

-- Procedure: Add department with default settings
DELIMITER //
CREATE PROCEDURE `sp_create_department`(
    IN p_name VARCHAR(100),
    IN p_description TEXT,
    IN p_parent_id INT,
    IN p_manager_type ENUM('single', 'team'),
    IN p_created_by VARCHAR(50)
)
BEGIN
    DECLARE v_dept_id INT;

    -- Insert department
    INSERT INTO departments (name, description, parent_id, manager_type, status, created_by)
    VALUES (p_name, p_description, p_parent_id, p_manager_type, 'active', p_created_by);

    SET v_dept_id = LAST_INSERT_ID();

    -- Insert default settings
    INSERT INTO department_settings (department_id, business_hours_start, business_hours_end,
                                     timezone, working_days, overflow_action, voicemail_enabled)
    VALUES (v_dept_id, '09:00:00', '17:00:00', 'America/New_York', '[1,2,3,4,5]', 'voicemail', 1);

    SELECT v_dept_id AS department_id;
END//
DELIMITER ;

-- Procedure: Assign manager to department
DELIMITER //
CREATE PROCEDURE `sp_assign_department_manager`(
    IN p_department_id INT,
    IN p_username VARCHAR(50),
    IN p_extension VARCHAR(20),
    IN p_role ENUM('manager', 'assistant_manager', 'supervisor', 'team_lead'),
    IN p_is_primary TINYINT(1),
    IN p_assigned_by VARCHAR(50)
)
BEGIN
    -- If this is primary manager, unset other primary managers
    IF p_is_primary = 1 THEN
        UPDATE department_managers
        SET is_primary = 0
        WHERE department_id = p_department_id;
    END IF;

    -- Insert or update manager assignment
    INSERT INTO department_managers (department_id, username, extension, role, is_primary, assigned_by, status)
    VALUES (p_department_id, p_username, p_extension, p_role, p_is_primary, p_assigned_by, 'active')
    ON DUPLICATE KEY UPDATE
        extension = p_extension,
        role = p_role,
        is_primary = p_is_primary,
        status = 'active';
END//
DELIMITER ;

-- ============================================================================
-- Triggers
-- ============================================================================

-- Trigger: Auto-set team lead when team member is added as lead
DELIMITER //
CREATE TRIGGER `tr_update_team_lead`
AFTER INSERT ON `team_members`
FOR EACH ROW
BEGIN
    IF NEW.role = 'lead' THEN
        UPDATE teams
        SET team_lead_username = NEW.username,
            team_lead_extension = NEW.extension
        WHERE id = NEW.team_id;
    END IF;
END//
DELIMITER ;

-- Trigger: Validate max team members
DELIMITER //
CREATE TRIGGER `tr_validate_team_size`
BEFORE INSERT ON `team_members`
FOR EACH ROW
BEGIN
    DECLARE v_max_members INT;
    DECLARE v_current_count INT;

    SELECT max_members INTO v_max_members
    FROM teams
    WHERE id = NEW.team_id;

    IF v_max_members > 0 THEN
        SELECT COUNT(*) INTO v_current_count
        FROM team_members
        WHERE team_id = NEW.team_id AND status = 'active';

        IF v_current_count >= v_max_members THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Team has reached maximum member capacity';
        END IF;
    END IF;
END//
DELIMITER ;

DELIMITER ;

-- ============================================================================
-- Grant Permissions (adjust username as needed)
-- ============================================================================

-- GRANT ALL PRIVILEGES ON flexpbxuser_flexpbx.* TO 'flexpbxuser_flexpbxserver'@'localhost';
-- FLUSH PRIVILEGES;

-- ============================================================================
-- Installation Complete
-- ============================================================================
-- To install this schema, run:
-- mysql -u flexpbxuser_flexpbxserver -p flexpbxuser_flexpbx < department_management_schema.sql
-- ============================================================================
