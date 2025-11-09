<?php
/**
 * Setup SMS Provider Tables - Simple Script
 */
$config = require __DIR__ . '/config.php';
$pdo = new PDO(
    "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
    $config['db_user'],
    $config['db_password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Table 1: sms_providers
$pdo->exec("CREATE TABLE IF NOT EXISTS sms_providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_name VARCHAR(50) NOT NULL,
    provider_type ENUM('textnow', 'google_voice', 'twilio', 'callcentric', 'other') NOT NULL,
    account_data TEXT,
    phone_number VARCHAR(20),
    enabled TINYINT(1) DEFAULT 1,
    is_default TINYINT(1) DEFAULT 0,
    priority INT DEFAULT 0,
    rate_limit_per_minute INT DEFAULT 60,
    daily_sms_limit INT DEFAULT 1000,
    daily_call_limit INT DEFAULT 500,
    total_sms_sent INT DEFAULT 0,
    total_sms_received INT DEFAULT 0,
    total_calls_made INT DEFAULT 0,
    total_calls_received INT DEFAULT 0,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_provider_type (provider_type),
    INDEX idx_enabled (enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "✓ sms_providers\n";

// Table 2: call_logs
$pdo->exec("CREATE TABLE IF NOT EXISTS call_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id INT NOT NULL,
    provider_type ENUM('textnow', 'google_voice', 'twilio', 'other') NOT NULL,
    direction ENUM('inbound', 'outbound') NOT NULL,
    from_number VARCHAR(20) NOT NULL,
    to_number VARCHAR(20) NOT NULL,
    status ENUM('queued', 'ringing', 'in-progress', 'completed', 'busy', 'failed', 'no-answer', 'canceled') DEFAULT 'queued',
    duration INT DEFAULT 0,
    ring_duration INT DEFAULT 0,
    call_sid VARCHAR(100),
    provider_data JSON,
    recording_url VARCHAR(500),
    recording_duration INT DEFAULT 0,
    extension_id INT DEFAULT NULL,
    extension_number VARCHAR(20) DEFAULT NULL,
    initiated_at TIMESTAMP NULL,
    answered_at TIMESTAMP NULL,
    ended_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_provider_id (provider_id),
    INDEX idx_call_sid (call_sid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "✓ call_logs\n";

// Table 3: provider_phone_numbers
$pdo->exec("CREATE TABLE IF NOT EXISTS provider_phone_numbers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id INT NOT NULL,
    provider_type ENUM('textnow', 'google_voice', 'twilio', 'other') NOT NULL,
    phone_number VARCHAR(20) NOT NULL UNIQUE,
    friendly_name VARCHAR(100),
    capabilities JSON,
    voice_url VARCHAR(500),
    sms_url VARCHAR(500),
    status_callback_url VARCHAR(500),
    is_active TINYINT(1) DEFAULT 1,
    is_primary TINYINT(1) DEFAULT 0,
    number_sid VARCHAR(100),
    provider_data JSON,
    purchased_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_provider_id (provider_id),
    INDEX idx_phone_number (phone_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "✓ provider_phone_numbers\n";

// Table 4: sms_provider_config
$pdo->exec("CREATE TABLE IF NOT EXISTS sms_provider_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id INT NOT NULL UNIQUE,
    oauth_client_id VARCHAR(255),
    oauth_client_secret TEXT,
    oauth_refresh_token TEXT,
    oauth_access_token TEXT,
    oauth_token_expires_at TIMESTAMP NULL,
    api_key TEXT,
    api_secret TEXT,
    account_sid VARCHAR(100),
    auth_token TEXT,
    webhook_base_url VARCHAR(500),
    webhook_secret VARCHAR(100),
    features JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "✓ sms_provider_config\n";

// Table 5: sms_usage_statistics
$pdo->exec("CREATE TABLE IF NOT EXISTS sms_usage_statistics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id INT NOT NULL,
    stat_date DATE NOT NULL,
    sms_sent INT DEFAULT 0,
    sms_received INT DEFAULT 0,
    mms_sent INT DEFAULT 0,
    mms_received INT DEFAULT 0,
    calls_made INT DEFAULT 0,
    calls_received INT DEFAULT 0,
    total_call_duration INT DEFAULT 0,
    total_cost DECIMAL(10,4) DEFAULT 0.0000,
    currency VARCHAR(3) DEFAULT 'USD',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_provider_date (provider_id, stat_date),
    INDEX idx_stat_date (stat_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "✓ sms_usage_statistics\n";

// Table 6: webhook_logs
$pdo->exec("CREATE TABLE IF NOT EXISTS webhook_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id INT,
    provider_type ENUM('textnow', 'google_voice', 'twilio', 'other'),
    webhook_type VARCHAR(50),
    http_method VARCHAR(10),
    remote_ip VARCHAR(45),
    request_headers JSON,
    request_body TEXT,
    query_params JSON,
    response_status INT,
    response_body TEXT,
    processed TINYINT(1) DEFAULT 0,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_provider_id (provider_id),
    INDEX idx_webhook_type (webhook_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "✓ webhook_logs\n";

// Insert default providers
$pdo->exec("INSERT IGNORE INTO sms_providers (provider_name, provider_type, enabled, priority) VALUES
    ('TextNow', 'textnow', 0, 3),
    ('Google Voice', 'google_voice', 0, 2),
    ('Twilio', 'twilio', 0, 1)");
echo "✓ Default providers inserted\n";

echo "\nAll tables created successfully!\n";
