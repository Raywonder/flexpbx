#!/usr/bin/env php
<?php
/**
 * FlexPBX Email Queue Processor
 *
 * Cron script to process email queue
 * Run this every 5 minutes via cron:
 * */5 * * * * /usr/bin/php /home/flexpbxuser/public_html/scripts/process-email-queue.php
 *
 * @author FlexPBX
 * @version 1.0.0
 * @created 2025-10-17
 */

// Set execution time limit
set_time_limit(300); // 5 minutes

// Change to script directory
chdir(dirname(__FILE__));

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/EmailService.php';

// Log file
$log_file = __DIR__ . '/../logs/email-queue-processor.log';

/**
 * Log message
 */
function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    echo $log_entry;
}

try {
    logMessage("=== Email Queue Processor Started ===");

    // Initialize email service
    $emailService = new EmailService();

    // Process queue (50 emails per run)
    $limit = 50;
    logMessage("Processing up to $limit emails from queue...");

    $processed = $emailService->processQueue($limit);

    logMessage("Processed $processed emails successfully");

    // Get queue summary
    $summary = $emailService->getQueueSummary();
    foreach ($summary as $status) {
        logMessage("Queue status '{$status['status']}': {$status['count']} emails");
    }

    // Process digest emails if needed
    $current_minute = intval(date('i'));
    $current_hour = intval(date('H'));

    // Process hourly digests at the top of every hour
    if ($current_minute < 5) {
        logMessage("Processing hourly digest emails...");
        $hourly_count = $emailService->processDigests('hourly');
        logMessage("Sent $hourly_count hourly digest emails");
    }

    // Process daily digests at 9:00 AM (or configured time)
    if ($current_hour === 9 && $current_minute < 5) {
        logMessage("Processing daily digest emails...");
        $daily_count = $emailService->processDigests('daily');
        logMessage("Sent $daily_count daily digest emails");
    }

    // Clean up old logs (run once per day at midnight)
    if ($current_hour === 0 && $current_minute < 5) {
        logMessage("Cleaning up old email logs (older than 30 days)...");
        $deleted = $emailService->clearOldLogs(30);
        logMessage("Deleted $deleted old log entries");
    }

    logMessage("=== Email Queue Processor Completed ===\n");

} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    logMessage("=== Email Queue Processor Failed ===\n");
    exit(1);
}

exit(0);
