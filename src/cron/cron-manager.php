<?php
/**
 * FlexPBX Internal Cron Manager
 * Manages and executes scheduled tasks without relying on system cron
 *
 * This script should be called by a single system cron job that runs every minute:
 * * * * * * /usr/bin/php /home/flexpbxuser/public_html/cron/cron-manager.php
 */

require_once __DIR__ . '/../config/database.php';

$log_file = __DIR__ . '/../../logs/cron-manager.log';

function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

// Create cron_jobs table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cron_jobs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            job_name VARCHAR(100) UNIQUE NOT NULL,
            job_description TEXT,
            script_path VARCHAR(255) NOT NULL,
            schedule_type ENUM('minutes', 'hourly', 'daily', 'weekly', 'monthly') DEFAULT 'hourly',
            schedule_value INT DEFAULT 5,
            last_run DATETIME NULL,
            next_run DATETIME NULL,
            enabled TINYINT(1) DEFAULT 1,
            run_count INT DEFAULT 0,
            last_status VARCHAR(50) NULL,
            last_error TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Insert default cron jobs
    $pdo->exec("
        INSERT IGNORE INTO cron_jobs (job_name, job_description, script_path, schedule_type, schedule_value)
        VALUES
        ('setup_auto_complete', 'Auto-complete setup checklist items', '/cron/auto-complete-setup-checks.php', 'minutes', 5),
        ('maintenance_check', 'Check and update maintenance mode status', '/cron/maintenance-check.php', 'minutes', 10),
        ('cleanup_logs', 'Clean up old log files', '/cron/cleanup-logs.php', 'daily', 1),
        ('backup_database', 'Backup database', '/cron/backup-database.php', 'daily', 1),
        ('update_statistics', 'Update call statistics', '/cron/update-statistics.php', 'hourly', 1)
    ");

    logMessage("Cron Manager started");

    // Get all enabled cron jobs that need to run
    $stmt = $pdo->query("
        SELECT * FROM cron_jobs
        WHERE enabled = 1
        AND (
            next_run IS NULL
            OR next_run <= NOW()
        )
        ORDER BY next_run ASC
    ");

    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($jobs as $job) {
        logMessage("Processing job: {$job['job_name']}");

        // Execute the job
        $script_path = __DIR__ . '/..' . $job['script_path'];

        if (!file_exists($script_path)) {
            logMessage("ERROR: Script not found: {$script_path}");
            updateJobStatus($pdo, $job['id'], 'error', 'Script file not found');
            continue;
        }

        try {
            // Execute in background
            $output = [];
            $return_var = 0;
            exec("php {$script_path} > /dev/null 2>&1 &", $output, $return_var);

            // Update job status
            $next_run = calculateNextRun($job['schedule_type'], $job['schedule_value']);
            updateJobStatus($pdo, $job['id'], 'success', null, $next_run);

            logMessage("✓ Job executed: {$job['job_name']} - Next run: {$next_run}");

        } catch (Exception $e) {
            logMessage("ERROR executing {$job['job_name']}: " . $e->getMessage());
            updateJobStatus($pdo, $job['id'], 'error', $e->getMessage());
        }
    }

    logMessage("Cron Manager finished processing " . count($jobs) . " jobs");

} catch (Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage());
}

function updateJobStatus($pdo, $job_id, $status, $error = null, $next_run = null) {
    if ($next_run === null) {
        // Calculate next run based on current job schedule
        $job_stmt = $pdo->prepare("SELECT schedule_type, schedule_value FROM cron_jobs WHERE id = ?");
        $job_stmt->execute([$job_id]);
        $job = $job_stmt->fetch(PDO::FETCH_ASSOC);
        $next_run = calculateNextRun($job['schedule_type'], $job['schedule_value']);
    }

    $stmt = $pdo->prepare("
        UPDATE cron_jobs
        SET last_run = NOW(),
            next_run = :next_run,
            run_count = run_count + 1,
            last_status = :status,
            last_error = :error
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $job_id,
        'next_run' => $next_run,
        'status' => $status,
        'error' => $error
    ]);
}

function calculateNextRun($schedule_type, $schedule_value) {
    $now = new DateTime();

    switch ($schedule_type) {
        case 'minutes':
            $now->modify("+{$schedule_value} minutes");
            break;

        case 'hourly':
            $now->modify("+{$schedule_value} hours");
            break;

        case 'daily':
            $now->modify("+{$schedule_value} days");
            break;

        case 'weekly':
            $now->modify("+{$schedule_value} weeks");
            break;

        case 'monthly':
            $now->modify("+{$schedule_value} months");
            break;

        default:
            $now->modify("+1 hour");
    }

    return $now->format('Y-m-d H:i:s');
}
