<?php
// FlexPBX Configuration File
// Generated: 2025-10-13 18:17:00

// Database Configuration
$DB_HOST = "localhost";
$DB_PORT = "3306";
$DB_NAME = "flexpbxuser_flexpbx";
$DB_USER = "flexpbxuser_flexpbxserver";
$DB_PASS = "DomDomRW93!";

// API Configuration
$API_KEY = "flexpbx_api_8603f84b113de94f6876b99bd7003adf";
$SERVER_MODE = "update_existing";

// Service Configuration
$SERVICES_ENABLED = true;
$AUTO_START_SERVICES = true;
$CRON_ENABLED = true;

// Security Settings
$REQUIRE_HTTPS = false;
$SESSION_TIMEOUT = 3600; // 1 hour
$MAX_CONNECTIONS = 100;

// Logging
$LOG_LEVEL = "INFO";
$LOG_ROTATION = true;
$LOG_MAX_SIZE = "10M";

// Features
$FEATURES = [
    "desktop_clients" => true,
    "mobile_clients" => true,
    "web_dashboard" => true,
    "auto_updates" => true,
    "remote_management" => true,
    "ivr_system" => true
];

?>