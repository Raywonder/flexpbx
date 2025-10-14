<?php
/**
 * FlexPBX Server Configuration
 * Database and server settings for desktop client connections
 */

return [
    // Database configuration
    'db_host' => 'localhost',
    'db_name' => 'flexpbxuser_flexpbx',
    'db_user' => 'flexpbxuser_flexpbxserver',
    'db_password' => 'DomDomRW93!',

    // API configuration
    'api_key' => 'flexpbx_api_8603f84b113de94f6876b99bd7003adf',
    'api_version' => '1.1.0',

    // Server configuration
    'server_name' => 'FlexPBX Remote Server',
    'max_connections_per_client' => 10,
    'default_connection_timeout' => 300, // 5 minutes

    // Update server configuration
    'update_server' => [
        'enabled' => true,
        'check_interval' => 3600, // 1 hour
        'auto_download' => false,
        'download_path' => '/var/www/flexpbx/updates/',
        'supported_platforms' => ['darwin', 'win32', 'linux']
    ],

    // Security settings
    'security' => [
        'require_https' => true,
        'cors_enabled' => true,
        'rate_limiting' => [
            'enabled' => true,
            'max_requests_per_minute' => 60
        ]
    ],

    // Logging configuration
    'logging' => [
        'enabled' => true,
        'level' => 'INFO', // DEBUG, INFO, WARNING, ERROR
        'file' => '/var/log/flexpbx/connections.log',
        'max_size' => '100MB',
        'rotate' => true
    ],

    // Module restart configuration
    'modules' => [
        'asterisk' => [
            'restart_command' => 'systemctl reload asterisk',
            'check_command' => 'systemctl is-active asterisk',
            'requires_sudo' => true
        ],
        'nginx' => [
            'restart_command' => 'systemctl reload nginx',
            'check_command' => 'nginx -t',
            'requires_sudo' => true
        ],
        'api' => [
            'restart_command' => 'systemctl restart flexpbx-api',
            'check_command' => 'systemctl is-active flexpbx-api',
            'requires_sudo' => true
        ]
    ],

    // Server restart configuration
    'server_restart' => [
        'graceful_delay' => 30, // seconds
        'force_delay' => 5, // seconds
        'restart_command' => 'systemctl restart flexpbx-server',
        'requires_confirmation' => true
    ],

    // Client connection limits by type
    'connection_limits' => [
        'admin' => [
            'default' => 5,
            'premium' => 10,
            'enterprise' => 100
        ],
        'desktop' => [
            'default' => 1,
            'premium' => 3,
            'enterprise' => 10
        ]
    ],

    // Auto-connect settings
    'auto_connect' => [
        'enabled' => true,
        'max_retry_attempts' => 3,
        'retry_delay' => 5000, // milliseconds
        'blacklist_duration' => 3600 // 1 hour
    ],

    // SSH deployment settings (for auto-updates)
    'deployment' => [
        'ssh_host' => 'flexpbx.devinecreations.net',
        'ssh_port' => 450,
        'ssh_user' => 'flexpbxuser',
        'ssh_key_path' => '/etc/flexpbx/ssh/deployment_key',
        'remote_path' => '/opt/flexpbx/',
        'backup_path' => '/opt/flexpbx/backups/'
    ]
];
?>