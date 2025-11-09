<?php
/**
 * FlexPBX Auto-Complete Setup Checks
 * Internal cron job to detect and auto-complete setup checklist items
 *
 * Run frequency: Every 5 minutes
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/config.php';

$log_file = __DIR__ . '/../../logs/setup-auto-complete.log';

function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

logMessage("Starting auto-complete check...");

try {
    // Get incomplete checklist items
    $stmt = $pdo->query("
        SELECT check_key, check_name
        FROM setup_checklist
        WHERE is_completed = 0
    ");
    $incomplete_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $completed_count = 0;

    foreach ($incomplete_items as $item) {
        $should_complete = false;
        $detection_method = '';

        switch ($item['check_key']) {
            case 'database_configured':
                // Check if database connection works and tables exist
                try {
                    $tables_stmt = $pdo->query("SHOW TABLES");
                    $tables = $tables_stmt->fetchAll(PDO::FETCH_COLUMN);
                    // If we have core tables, database is configured
                    $required_tables = ['system_maintenance', 'setup_checklist'];
                    $has_tables = count(array_intersect($required_tables, $tables)) >= 2;
                    if ($has_tables) {
                        $should_complete = true;
                        $detection_method = 'Database connection active and tables exist';
                    }
                } catch (Exception $e) {
                    logMessage("Database check failed: " . $e->getMessage());
                }
                break;

            case 'admin_created':
                // Check if admin user exists in users table
                try {
                    $admin_stmt = $pdo->query("
                        SELECT COUNT(*) as count FROM users
                        WHERE role = 'admin' OR is_admin = 1
                        LIMIT 1
                    ");
                    $admin_count = $admin_stmt->fetchColumn();
                    if ($admin_count > 0) {
                        $should_complete = true;
                        $detection_method = 'Admin user found in database';
                    }
                } catch (Exception $e) {
                    // Table might not exist yet
                    logMessage("Admin check failed: " . $e->getMessage());
                }
                break;

            case 'email_configured':
                // Check if email settings are configured
                try {
                    $email_stmt = $pdo->query("
                        SELECT COUNT(*) as count FROM system_settings
                        WHERE setting_key IN ('smtp_host', 'smtp_user', 'smtp_password')
                        AND setting_value IS NOT NULL
                        AND setting_value != ''
                    ");
                    $configured_count = $email_stmt->fetchColumn();
                    if ($configured_count >= 3) {
                        $should_complete = true;
                        $detection_method = 'Email settings configured in database';
                    }
                } catch (Exception $e) {
                    logMessage("Email check failed: " . $e->getMessage());
                }
                break;

            case 'sip_trunk_added':
                // Check if SIP trunk exists
                try {
                    $trunk_stmt = $pdo->query("
                        SELECT COUNT(*) as count FROM sip_trunks
                        WHERE active = 1
                        LIMIT 1
                    ");
                    $trunk_count = $trunk_stmt->fetchColumn();
                    if ($trunk_count > 0) {
                        $should_complete = true;
                        $detection_method = 'Active SIP trunk found';
                    }
                } catch (Exception $e) {
                    logMessage("Trunk check failed: " . $e->getMessage());
                }
                break;

            case 'extension_created':
                // Check if extension exists
                try {
                    $ext_stmt = $pdo->query("
                        SELECT COUNT(*) as count FROM extensions
                        WHERE enabled = 1
                        LIMIT 1
                    ");
                    $ext_count = $ext_stmt->fetchColumn();
                    if ($ext_count > 0) {
                        $should_complete = true;
                        $detection_method = 'Active extension found';
                    }
                } catch (Exception $e) {
                    logMessage("Extension check failed: " . $e->getMessage());
                }
                break;

            case 'security_configured':
                // Check if security settings are enabled
                try {
                    $security_stmt = $pdo->query("
                        SELECT COUNT(*) as count FROM system_settings
                        WHERE setting_key IN ('fail2ban_enabled', 'firewall_enabled', 'strong_passwords_required')
                        AND setting_value = '1'
                    ");
                    $security_count = $security_stmt->fetchColumn();
                    if ($security_count >= 2) {
                        $should_complete = true;
                        $detection_method = 'Security features enabled';
                    }
                } catch (Exception $e) {
                    logMessage("Security check failed: " . $e->getMessage());
                }
                break;

            case 'backup_configured':
                // Check if backup schedule exists
                try {
                    $backup_stmt = $pdo->query("
                        SELECT COUNT(*) as count FROM cron_jobs
                        WHERE job_name LIKE '%backup%'
                        AND enabled = 1
                    ");
                    $backup_count = $backup_stmt->fetchColumn();
                    if ($backup_count > 0) {
                        $should_complete = true;
                        $detection_method = 'Backup cron job configured';
                    }
                } catch (Exception $e) {
                    logMessage("Backup check failed: " . $e->getMessage());
                }
                break;

            case 'ssl_certificate':
                // Check if SSL is configured (check if HTTPS is working)
                $ssl_file = '/var/cpanel/ssl/domain_tls/' . $_SERVER['HTTP_HOST'] . '/combined';
                if (file_exists($ssl_file)) {
                    $should_complete = true;
                    $detection_method = 'SSL certificate file found';
                }
                break;

            case 'custom_branding':
                // Check if logo file exists
                $logo_file = __DIR__ . '/../assets/images/logo.png';
                if (file_exists($logo_file) && filesize($logo_file) > 1000) {
                    $should_complete = true;
                    $detection_method = 'Custom logo file found';
                }
                break;

            case 'license_activated':
                // Check if license key is entered
                try {
                    $license_stmt = $pdo->query("
                        SELECT setting_value FROM system_settings
                        WHERE setting_key = 'license_key'
                        AND setting_value IS NOT NULL
                        AND setting_value != ''
                    ");
                    $license = $license_stmt->fetchColumn();
                    if ($license) {
                        $should_complete = true;
                        $detection_method = 'License key found in settings';
                    }
                } catch (Exception $e) {
                    logMessage("License check failed: " . $e->getMessage());
                }
                break;
        }

        // Auto-complete if detected
        if ($should_complete) {
            $update_stmt = $pdo->prepare("
                UPDATE setup_checklist
                SET is_completed = 1,
                    completed_at = NOW(),
                    completed_by = 'system-auto'
                WHERE check_key = :check_key
            ");
            $update_stmt->execute(['check_key' => $item['check_key']]);
            $completed_count++;
            logMessage("✓ Auto-completed: {$item['check_name']} - {$detection_method}");
        }
    }

    // Check if all required items are now complete
    if ($completed_count > 0) {
        $required_stmt = $pdo->query("
            SELECT
                SUM(CASE WHEN is_required = 1 THEN 1 ELSE 0 END) as required,
                SUM(CASE WHEN is_required = 1 AND is_completed = 1 THEN 1 ELSE 0 END) as required_completed
            FROM setup_checklist
        ");
        $result = $required_stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['required'] == $result['required_completed']) {
            // Auto-disable maintenance mode
            $pdo->exec("
                UPDATE system_maintenance
                SET is_active = 0,
                    disabled_at = NOW(),
                    maintenance_message = 'Setup completed successfully (auto-detected)'
                WHERE maintenance_mode_type = 'auto'
            ");
            logMessage("✓ All required items complete - Maintenance mode disabled");
        }
    }

    logMessage("Auto-complete check finished. Completed {$completed_count} items.");

} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
}
