-- FlexPBX Remote Server - Database Schema
-- Version: 1.0.0
-- Supports: MySQL 8.0+, MariaDB 10.4+

-- Create FlexPBX database if it doesn't exist
CREATE DATABASE IF NOT EXISTS flexpbx CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE flexpbx;

-- Users and Authentication Table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'user', 'operator') DEFAULT 'user',
    is_active BOOLEAN DEFAULT TRUE,
    api_key VARCHAR(64) UNIQUE,
    pincode VARCHAR(6),
    pincode_expires DATETIME,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Extensions Table
CREATE TABLE IF NOT EXISTS extensions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    extension_number VARCHAR(10) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    password_hash VARCHAR(255) NOT NULL,
    user_id INT,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_registered DATETIME,
    registration_ip VARCHAR(45),
    codec_preferences JSON,
    call_forwarding JSON,
    voicemail_enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Call Detail Records (CDR)
CREATE TABLE IF NOT EXISTS call_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    call_id VARCHAR(128) UNIQUE NOT NULL,
    caller_extension VARCHAR(10),
    caller_number VARCHAR(50),
    called_extension VARCHAR(10),
    called_number VARCHAR(50),
    call_direction ENUM('inbound', 'outbound', 'internal') NOT NULL,
    call_status ENUM('answered', 'busy', 'no_answer', 'failed') NOT NULL,
    start_time DATETIME NOT NULL,
    answer_time DATETIME,
    end_time DATETIME,
    duration INT DEFAULT 0,
    billable_duration INT DEFAULT 0,
    recording_file VARCHAR(255),
    cost DECIMAL(10,4) DEFAULT 0.0000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- SIP Trunks Configuration
CREATE TABLE IF NOT EXISTS sip_trunks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    provider VARCHAR(100) NOT NULL,
    host VARCHAR(255) NOT NULL,
    port INT DEFAULT 5060,
    username VARCHAR(100),
    password VARCHAR(255),
    codec_preferences JSON,
    registration_required BOOLEAN DEFAULT TRUE,
    registration_status ENUM('registered', 'unregistered', 'failed') DEFAULT 'unregistered',
    last_registration DATETIME,
    max_channels INT DEFAULT 30,
    current_channels INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- System Configuration
CREATE TABLE IF NOT EXISTS system_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value TEXT,
    config_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- API Keys and Access Control
CREATE TABLE IF NOT EXISTS api_keys (
    id INT PRIMARY KEY AUTO_INCREMENT,
    key_name VARCHAR(100) NOT NULL,
    api_key VARCHAR(64) UNIQUE NOT NULL,
    user_id INT,
    permissions JSON,
    rate_limit INT DEFAULT 1000,
    requests_today INT DEFAULT 0,
    last_used DATETIME,
    expires_at DATETIME,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Device Management (Desktop clients, phones, etc.)
CREATE TABLE IF NOT EXISTS devices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    device_name VARCHAR(100) NOT NULL,
    device_type ENUM('desktop_mac', 'desktop_windows', 'flexphone_mobile', 'flexphone_desktop', 'sip_phone') NOT NULL,
    user_id INT,
    extension_id INT,
    device_identifier VARCHAR(255) UNIQUE,
    pincode VARCHAR(6),
    pincode_expires DATETIME,
    last_connected DATETIME,
    connection_ip VARCHAR(45),
    is_authorized BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    device_info JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (extension_id) REFERENCES extensions(id) ON DELETE SET NULL
);

-- System Logs
CREATE TABLE IF NOT EXISTS system_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    log_level ENUM('DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL') NOT NULL,
    component VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    user_id INT,
    ip_address VARCHAR(45),
    additional_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Backup Records
CREATE TABLE IF NOT EXISTS backups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    backup_name VARCHAR(255) NOT NULL,
    backup_type ENUM('full', 'database', 'config') NOT NULL,
    file_path VARCHAR(500),
    file_size BIGINT,
    status ENUM('creating', 'completed', 'failed') DEFAULT 'creating',
    created_by INT,
    compression_type VARCHAR(20) DEFAULT 'gzip',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Conference Rooms
CREATE TABLE IF NOT EXISTS conferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    conference_number VARCHAR(20) UNIQUE NOT NULL,
    conference_name VARCHAR(100) NOT NULL,
    pin VARCHAR(20),
    admin_pin VARCHAR(20),
    max_participants INT DEFAULT 50,
    current_participants INT DEFAULT 0,
    recording_enabled BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert default system configuration
INSERT IGNORE INTO system_config (config_key, config_value, config_type, description, is_public) VALUES
('system_version', '1.0.0', 'string', 'FlexPBX Server Version', TRUE),
('sip_port', '5060', 'integer', 'SIP Listen Port', FALSE),
('rtp_start_port', '10000', 'integer', 'RTP Start Port Range', FALSE),
('rtp_end_port', '20000', 'integer', 'RTP End Port Range', FALSE),
('recording_enabled', 'false', 'boolean', 'Call Recording Enabled', FALSE),
('api_rate_limit', '1000', 'integer', 'API Rate Limit (requests/hour)', FALSE),
('pincode_expiry_minutes', '60', 'integer', 'Device Pincode Expiry (minutes)', FALSE),
('max_call_duration', '14400', 'integer', 'Maximum Call Duration (seconds)', FALSE),
('domain_primary', 'flexpbx.devinecreations.net', 'string', 'Primary Domain', TRUE),
('domains_api', '["api.devinecreations.net","api.tappedin.fm","api.devine-creations.com","api.raywonderis.me"]', 'json', 'API Domains', TRUE);

-- Insert default admin user
INSERT IGNORE INTO users (username, email, password_hash, full_name, role, api_key) VALUES
('admin', 'admin@flexpbx.local', SHA2('FlexPBX2024!', 256), 'System Administrator', 'admin', SHA2(CONCAT('admin', NOW()), 256));

-- Insert default API key
INSERT IGNORE INTO api_keys (key_name, api_key, user_id, permissions, rate_limit) VALUES
('Default Admin Key', 'flexpbx_api_2024', 1, '{"admin": true, "read": true, "write": true}', 10000);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_extensions_number ON extensions(extension_number);
CREATE INDEX IF NOT EXISTS idx_extensions_user ON extensions(user_id);
CREATE INDEX IF NOT EXISTS idx_call_records_time ON call_records(start_time);
CREATE INDEX IF NOT EXISTS idx_call_records_caller ON call_records(caller_extension);
CREATE INDEX IF NOT EXISTS idx_call_records_called ON call_records(called_extension);
CREATE INDEX IF NOT EXISTS idx_devices_user ON devices(user_id);
CREATE INDEX IF NOT EXISTS idx_devices_type ON devices(device_type);
CREATE INDEX IF NOT EXISTS idx_devices_pincode ON devices(pincode);
CREATE INDEX IF NOT EXISTS idx_system_logs_time ON system_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_system_logs_level ON system_logs(log_level);
CREATE INDEX IF NOT EXISTS idx_api_keys_key ON api_keys(api_key);

-- Create views for common queries
CREATE VIEW IF NOT EXISTS v_active_extensions AS
SELECT
    e.extension_number,
    e.display_name,
    e.email,
    e.status,
    e.last_registered,
    u.full_name as user_name,
    u.role as user_role
FROM extensions e
LEFT JOIN users u ON e.user_id = u.id
WHERE e.status = 'active';

CREATE VIEW IF NOT EXISTS v_call_summary AS
SELECT
    DATE(start_time) as call_date,
    call_direction,
    COUNT(*) as total_calls,
    SUM(duration) as total_duration,
    AVG(duration) as avg_duration,
    SUM(CASE WHEN call_status = 'answered' THEN 1 ELSE 0 END) as answered_calls
FROM call_records
GROUP BY DATE(start_time), call_direction;

CREATE VIEW IF NOT EXISTS v_device_status AS
SELECT
    d.device_name,
    d.device_type,
    d.last_connected,
    d.is_authorized,
    d.is_active,
    u.username,
    u.full_name,
    e.extension_number
FROM devices d
LEFT JOIN users u ON d.user_id = u.id
LEFT JOIN extensions e ON d.extension_id = e.id;

-- Create stored procedures for common operations
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS GenerateDevicePincode(IN device_id INT)
BEGIN
    DECLARE new_pincode VARCHAR(6);
    DECLARE expiry_time DATETIME;

    SET new_pincode = LPAD(FLOOR(RAND() * 1000000), 6, '0');
    SET expiry_time = DATE_ADD(NOW(), INTERVAL 60 MINUTE);

    UPDATE devices
    SET pincode = new_pincode, pincode_expires = expiry_time
    WHERE id = device_id;

    SELECT new_pincode as pincode, expiry_time as expires_at;
END//

CREATE PROCEDURE IF NOT EXISTS AuthorizeDevice(IN device_pincode VARCHAR(6), IN device_identifier VARCHAR(255))
BEGIN
    DECLARE device_count INT DEFAULT 0;

    SELECT COUNT(*) INTO device_count
    FROM devices
    WHERE pincode = device_pincode
    AND pincode_expires > NOW()
    AND device_identifier = device_identifier;

    IF device_count > 0 THEN
        UPDATE devices
        SET is_authorized = TRUE, last_connected = NOW(), pincode = NULL, pincode_expires = NULL
        WHERE pincode = device_pincode AND device_identifier = device_identifier;

        SELECT TRUE as authorized, 'Device authorized successfully' as message;
    ELSE
        SELECT FALSE as authorized, 'Invalid or expired pincode' as message;
    END IF;
END//

CREATE PROCEDURE IF NOT EXISTS LogSystemEvent(
    IN log_level VARCHAR(20),
    IN component VARCHAR(50),
    IN message TEXT,
    IN user_id INT,
    IN ip_address VARCHAR(45),
    IN additional_data JSON
)
BEGIN
    INSERT INTO system_logs (log_level, component, message, user_id, ip_address, additional_data)
    VALUES (log_level, component, message, user_id, ip_address, additional_data);
END//

DELIMITER ;

-- Grant privileges to flexpbx user (will be created during installation)
-- GRANT ALL PRIVILEGES ON flexpbx.* TO 'flexpbx'@'localhost';
-- FLUSH PRIVILEGES;