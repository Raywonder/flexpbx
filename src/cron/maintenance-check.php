<?php
/**
 * Maintenance Mode Check Cron
 * Verifies maintenance mode status and auto-adjusts based on setup progress
 */

require_once __DIR__ . '/../config/database.php';

$log_file = __DIR__ . '/../../logs/maintenance-check.log';

function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

logMessage("Maintenance check starting...");

try {
    // Get current maintenance status
    $maint_stmt = $pdo->query("
        SELECT is_active, maintenance_mode_type
        FROM system_maintenance
        ORDER BY id DESC
        LIMIT 1
    ");
    $maintenance = $maint_stmt->fetch(PDO::FETCH_ASSOC);

    // Get setup progress
    $progress_stmt = $pdo->query("
        SELECT
            SUM(CASE WHEN is_required = 1 THEN 1 ELSE 0 END) as required,
            SUM(CASE WHEN is_required = 1 AND is_completed = 1 THEN 1 ELSE 0 END) as required_completed
        FROM setup_checklist
    ");
    $progress = $progress_stmt->fetch(PDO::FETCH_ASSOC);

    $setup_complete = ($progress['required'] == $progress['required_completed']);

    // Only manage auto mode
    if ($maintenance && $maintenance['maintenance_mode_type'] === 'auto') {
        if ($setup_complete && $maintenance['is_active'] == 1) {
            // Setup is complete but maintenance still active - disable it
            $pdo->exec("
                UPDATE system_maintenance
                SET is_active = 0,
                    disabled_at = NOW(),
                    maintenance_message = 'Setup completed - system operational'
                WHERE maintenance_mode_type = 'auto'
            ");
            logMessage("✓ Setup complete - Disabled auto maintenance mode");

        } elseif (!$setup_complete && $maintenance['is_active'] == 0) {
            // Setup incomplete but maintenance disabled - re-enable it
            $pdo->exec("
                UPDATE system_maintenance
                SET is_active = 1,
                    enabled_at = NOW(),
                    maintenance_message = 'Setup in progress'
                WHERE maintenance_mode_type = 'auto'
            ");
            logMessage("✓ Setup incomplete - Re-enabled auto maintenance mode");
        }
    }

    logMessage("Maintenance check complete. Setup: " . ($setup_complete ? "Complete" : "Incomplete"));

} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
}
