-- FlexPBX SMS Providers Database Schema
-- Supports TextNow, Google Voice, Twilio, and future providers
-- Created: 2025-10-31

-- Create sms_providers table for provider configuration
CREATE TABLE IF NOT EXISTS sms_providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_name VARCHAR(50) NOT NULL COMMENT 'Display name for the provider',
    provider_type ENUM('textnow', 'google_voice', 'twilio', 'callcentric', 'other') NOT NULL,

    -- Configuration data (stored as encrypted JSON)
    account_data TEXT COMMENT 'Encrypted JSON containing API keys, tokens, etc',

    -- Provider phone number
    phone_number VARCHAR(20) COMMENT 'Primary phone number for this provider',

    -- Status and settings
    enabled TINYINT(1) DEFAULT 1,
    is_default TINYINT(1) DEFAULT 0 COMMENT 'Use this provider by default',
    priority INT DEFAULT 0 COMMENT 'Priority when multiple providers available (higher = preferred)',

    -- Rate limiting
    rate_limit_per_minute INT DEFAULT 60,
    daily_sms_limit INT DEFAULT 1000,
    daily_call_limit INT DEFAULT 500,

    -- Statistics
    total_sms_sent INT DEFAULT 0,
    total_sms_received INT DEFAULT 0,
    total_calls_made INT DEFAULT 0,
    total_calls_received INT DEFAULT 0,
    last_used_at TIMESTAMP NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_provider_type (provider_type),
    INDEX idx_enabled (enabled),
    INDEX idx_is_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create sms_messages table for message storage
CREATE TABLE IF NOT EXISTS sms_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Provider information
    provider_id INT NOT NULL,
    provider_type ENUM('textnow', 'google_voice', 'twilio', 'callcentric', 'other') NOT NULL,

    -- Message details
    direction ENUM('inbound', 'outbound') NOT NULL,
    message_type ENUM('sms', 'mms') DEFAULT 'sms',

    -- Phone numbers
    from_number VARCHAR(20) NOT NULL,
    to_number VARCHAR(20) NOT NULL,

    -- Message content
    message_body TEXT,
    media_urls JSON COMMENT 'Array of media URLs for MMS',

    -- Provider-specific data
    message_sid VARCHAR(100) COMMENT 'Provider message ID',
    provider_data JSON COMMENT 'Additional provider-specific data',

    -- Status tracking
    status ENUM('pending', 'queued', 'sent', 'delivered', 'failed', 'received') DEFAULT 'pending',
    error_message TEXT,

    -- Extension linking (optional)
    extension_id INT DEFAULT NULL,
    extension_number VARCHAR(20) DEFAULT NULL,

    -- Timestamps
    sent_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    received_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign key
    FOREIGN KEY (provider_id) REFERENCES sms_providers(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_provider_id (provider_id),
    INDEX idx_direction (direction),
    INDEX idx_status (status),
    INDEX idx_from_number (from_number),
    INDEX idx_to_number (to_number),
    INDEX idx_created_at (created_at),
    INDEX idx_extension (extension_number),
    INDEX idx_message_sid (message_sid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create call_logs table for call tracking
CREATE TABLE IF NOT EXISTS call_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Provider information
    provider_id INT NOT NULL,
    provider_type ENUM('textnow', 'google_voice', 'twilio', 'other') NOT NULL,

    -- Call details
    direction ENUM('inbound', 'outbound') NOT NULL,
    from_number VARCHAR(20) NOT NULL,
    to_number VARCHAR(20) NOT NULL,

    -- Call status
    status ENUM('queued', 'ringing', 'in-progress', 'completed', 'busy', 'failed', 'no-answer', 'canceled') DEFAULT 'queued',

    -- Call metrics
    duration INT DEFAULT 0 COMMENT 'Duration in seconds',
    ring_duration INT DEFAULT 0 COMMENT 'Time spent ringing in seconds',

    -- Provider-specific data
    call_sid VARCHAR(100) COMMENT 'Provider call ID',
    provider_data JSON COMMENT 'Additional provider-specific data',

    -- Recording information
    recording_url VARCHAR(500),
    recording_duration INT DEFAULT 0,

    -- Extension linking (optional)
    extension_id INT DEFAULT NULL,
    extension_number VARCHAR(20) DEFAULT NULL,

    -- Timestamps
    initiated_at TIMESTAMP NULL,
    answered_at TIMESTAMP NULL,
    ended_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign key
    FOREIGN KEY (provider_id) REFERENCES sms_providers(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_provider_id (provider_id),
    INDEX idx_direction (direction),
    INDEX idx_status (status),
    INDEX idx_from_number (from_number),
    INDEX idx_to_number (to_number),
    INDEX idx_created_at (created_at),
    INDEX idx_call_sid (call_sid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create provider_phone_numbers table for number management
CREATE TABLE IF NOT EXISTS provider_phone_numbers (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Provider association
    provider_id INT NOT NULL,
    provider_type ENUM('textnow', 'google_voice', 'twilio', 'other') NOT NULL,

    -- Number details
    phone_number VARCHAR(20) NOT NULL UNIQUE,
    friendly_name VARCHAR(100),

    -- Capabilities
    capabilities JSON COMMENT '{"voice": true, "sms": true, "mms": false}',

    -- Configuration
    voice_url VARCHAR(500) COMMENT 'Webhook URL for incoming calls',
    sms_url VARCHAR(500) COMMENT 'Webhook URL for incoming SMS',
    status_callback_url VARCHAR(500),

    -- Status
    is_active TINYINT(1) DEFAULT 1,
    is_primary TINYINT(1) DEFAULT 0,

    -- Provider-specific data
    number_sid VARCHAR(100) COMMENT 'Provider number ID',
    provider_data JSON,

    -- Timestamps
    purchased_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign key
    FOREIGN KEY (provider_id) REFERENCES sms_providers(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_provider_id (provider_id),
    INDEX idx_phone_number (phone_number),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create sms_provider_config table for detailed configuration
CREATE TABLE IF NOT EXISTS sms_provider_config (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Provider association
    provider_id INT NOT NULL UNIQUE,

    -- OAuth credentials (encrypted)
    oauth_client_id VARCHAR(255),
    oauth_client_secret TEXT,
    oauth_refresh_token TEXT,
    oauth_access_token TEXT,
    oauth_token_expires_at TIMESTAMP NULL,

    -- API credentials (encrypted)
    api_key TEXT,
    api_secret TEXT,
    account_sid VARCHAR(100),
    auth_token TEXT,

    -- Webhook configuration
    webhook_base_url VARCHAR(500),
    webhook_secret VARCHAR(100),

    -- Feature flags
    features JSON COMMENT 'Provider-specific feature configuration',

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign key
    FOREIGN KEY (provider_id) REFERENCES sms_providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create sms_usage_statistics table for tracking usage
CREATE TABLE IF NOT EXISTS sms_usage_statistics (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Provider association
    provider_id INT NOT NULL,

    -- Date for statistics
    stat_date DATE NOT NULL,

    -- Message counts
    sms_sent INT DEFAULT 0,
    sms_received INT DEFAULT 0,
    mms_sent INT DEFAULT 0,
    mms_received INT DEFAULT 0,

    -- Call counts
    calls_made INT DEFAULT 0,
    calls_received INT DEFAULT 0,
    total_call_duration INT DEFAULT 0 COMMENT 'Total duration in seconds',

    -- Costs (if available)
    total_cost DECIMAL(10,4) DEFAULT 0.0000,
    currency VARCHAR(3) DEFAULT 'USD',

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign key
    FOREIGN KEY (provider_id) REFERENCES sms_providers(id) ON DELETE CASCADE,

    -- Indexes
    UNIQUE KEY unique_provider_date (provider_id, stat_date),
    INDEX idx_stat_date (stat_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create webhook_logs table for debugging
CREATE TABLE IF NOT EXISTS webhook_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Provider information
    provider_id INT,
    provider_type ENUM('textnow', 'google_voice', 'twilio', 'other'),

    -- Webhook details
    webhook_type VARCHAR(50) COMMENT 'Type of webhook (sms, voice, status, etc)',
    http_method VARCHAR(10),
    remote_ip VARCHAR(45),

    -- Request data
    request_headers JSON,
    request_body TEXT,
    query_params JSON,

    -- Response data
    response_status INT,
    response_body TEXT,

    -- Processing
    processed TINYINT(1) DEFAULT 0,
    error_message TEXT,

    -- Timestamp
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_provider_id (provider_id),
    INDEX idx_webhook_type (webhook_type),
    INDEX idx_created_at (created_at),
    INDEX idx_processed (processed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default providers (disabled initially)
INSERT INTO sms_providers (provider_name, provider_type, enabled, priority) VALUES
    ('TextNow', 'textnow', 0, 3),
    ('Google Voice', 'google_voice', 0, 2),
    ('Twilio', 'twilio', 0, 1)
ON DUPLICATE KEY UPDATE provider_name = VALUES(provider_name);
