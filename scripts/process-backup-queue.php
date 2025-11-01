#!/usr/bin/env php
<?php
/**
 * FlexPBX Backup Queue Processor
 * Processes backup requests from the queue directory
 *
 * Created: October 24, 2025
 *
 * Usage: Run via cron every 5 minutes
 *   Run: crontab -e and add the cron entry from the .cron file
 *
 * Queue directory: /home/flexpbxuser/.backup-queue/
 * Status directory: /home/flexpbxuser/.backup-status/
 * Log file: /home/flexpbxuser/logs/backup-queue.log
 */

// Configuration
define('QUEUE_DIR', '/home/flexpbxuser/.backup-queue');
define('STATUS_DIR', '/home/flexpbxuser/.backup-status');
define('LOG_FILE', '/home/flexpbxuser/logs/backup-queue.log');
define('BACKUP_SCRIPT', '/usr/local/bin/flexpbx-backup');
define('STATUS_RETENTION_HOURS', 24);
define('MAX_EXECUTION_TIME', 600); // 10 minutes max per backup

// Ensure directories exist
@mkdir(QUEUE_DIR, 0755, true);
@mkdir(STATUS_DIR, 0755, true);
@mkdir(dirname(LOG_FILE), 0755, true);

// Start processing
logMessage("=== Backup Queue Processor Started ===");

// Clean up old status files first
cleanupOldStatusFiles();

// Get all pending backup requests
$requests = scanQueueDirectory();

if (empty($requests)) {
    logMessage("No backup requests in queue");
    logMessage("=== Backup Queue Processor Finished ===\n");
    exit(0);
}

logMessage("Found " . count($requests) . " backup request(s) in queue");

// Process each request
foreach ($requests as $requestFile) {
    processBackupRequest($requestFile);
}

logMessage("=== Backup Queue Processor Finished ===\n");
exit(0);

/**
 * Scan queue directory for pending requests
 */
function scanQueueDirectory() {
    $requests = [];

    if (!is_dir(QUEUE_DIR)) {
        logMessage("Queue directory does not exist: " . QUEUE_DIR, 'ERROR');
        return $requests;
    }

    $files = glob(QUEUE_DIR . '/*.json');

    if ($files === false) {
        logMessage("Failed to scan queue directory", 'ERROR');
        return $requests;
    }

    // Sort by timestamp (oldest first)
    usort($files, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });

    return $files;
}

/**
 * Process a single backup request
 */
function processBackupRequest($requestFile) {
    $requestId = basename($requestFile, '.json');

    logMessage("Processing request: $requestId");

    // Read request data
    $requestData = json_decode(file_get_contents($requestFile), true);

    if (!$requestData) {
        logMessage("Failed to parse request file: $requestFile", 'ERROR');
        unlink($requestFile);
        return;
    }

    // Validate request data
    if (!isset($requestData['type'])) {
        logMessage("Invalid request data: missing type", 'ERROR');
        createStatusFile($requestId, 'failed', $requestData, 'Invalid request: missing backup type');
        unlink($requestFile);
        return;
    }

    $type = $requestData['type'];
    $compress = $requestData['compress'] ?? true;
    $user = $requestData['user'] ?? 'unknown';

    // Validate backup type
    if (!in_array($type, ['flx', 'flxx', 'full'])) {
        logMessage("Invalid backup type: $type", 'ERROR');
        createStatusFile($requestId, 'failed', $requestData, "Invalid backup type: $type");
        unlink($requestFile);
        return;
    }

    // Update status to processing
    createStatusFile($requestId, 'processing', $requestData, 'Backup in progress');

    // Build backup command
    $command = buildBackupCommand($type, $compress);

    logMessage("Executing: $command");

    // Execute backup with timeout
    $startTime = time();
    $output = [];
    $returnCode = 0;

    exec("timeout " . MAX_EXECUTION_TIME . " $command 2>&1", $output, $returnCode);

    $duration = time() - $startTime;
    $outputText = implode("\n", $output);

    // Check result
    if ($returnCode === 0) {
        logMessage("Backup completed successfully in {$duration}s");

        // Extract backup file path from output
        $backupFile = extractBackupFilePath($outputText);

        createStatusFile($requestId, 'completed', $requestData, 'Backup completed successfully', [
            'duration' => $duration,
            'backup_file' => $backupFile,
            'output' => $outputText
        ]);
    } else if ($returnCode === 124) {
        // Timeout
        logMessage("Backup timed out after " . MAX_EXECUTION_TIME . "s", 'ERROR');
        createStatusFile($requestId, 'failed', $requestData, 'Backup timed out', [
            'duration' => $duration,
            'timeout' => MAX_EXECUTION_TIME,
            'output' => $outputText
        ]);
    } else {
        logMessage("Backup failed with return code: $returnCode", 'ERROR');
        createStatusFile($requestId, 'failed', $requestData, "Backup failed with return code: $returnCode", [
            'duration' => $duration,
            'return_code' => $returnCode,
            'output' => $outputText
        ]);
    }

    // Remove request from queue
    unlink($requestFile);
    logMessage("Request $requestId removed from queue");
}

/**
 * Build backup command based on type and options
 */
function buildBackupCommand($type, $compress) {
    $command = BACKUP_SCRIPT;

    // Handle "full" type - this means flxx with data
    if ($type === 'full') {
        $command .= " -t flxx -d";
    } else {
        $command .= " -t " . escapeshellarg($type);
    }

    // Add compression flag
    if ($compress) {
        $command .= " -c";
    }

    // Add verbose flag
    $command .= " -v";

    return $command;
}

/**
 * Extract backup file path from command output
 */
function extractBackupFilePath($output) {
    // Look for "Backup file: /path/to/file"
    if (preg_match('/Backup file:\s+(.+)$/m', $output, $matches)) {
        return trim($matches[1]);
    }

    // Look for "Backup created: /path/to/file"
    if (preg_match('/Backup created:\s+(.+)$/m', $output, $matches)) {
        return trim($matches[1]);
    }

    return null;
}

/**
 * Create or update status file
 */
function createStatusFile($requestId, $status, $requestData, $message, $additionalData = []) {
    $statusFile = STATUS_DIR . '/' . $requestId . '.json';

    $statusData = [
        'request_id' => $requestId,
        'status' => $status,
        'message' => $message,
        'type' => $requestData['type'] ?? 'unknown',
        'compressed' => $requestData['compress'] ?? false,
        'user' => $requestData['user'] ?? 'unknown',
        'queued_at' => $requestData['timestamp'] ?? null,
        'updated_at' => date('Y-m-d H:i:s'),
        'timestamp' => time()
    ];

    // Merge additional data
    $statusData = array_merge($statusData, $additionalData);

    file_put_contents($statusFile, json_encode($statusData, JSON_PRETTY_PRINT));

    logMessage("Status updated: $status - $message");
}

/**
 * Clean up old status files (older than retention period)
 */
function cleanupOldStatusFiles() {
    if (!is_dir(STATUS_DIR)) {
        return;
    }

    $files = glob(STATUS_DIR . '/*.json');
    $cutoffTime = time() - (STATUS_RETENTION_HOURS * 3600);
    $deletedCount = 0;

    foreach ($files as $file) {
        if (filemtime($file) < $cutoffTime) {
            if (unlink($file)) {
                $deletedCount++;
            }
        }
    }

    if ($deletedCount > 0) {
        logMessage("Cleaned up $deletedCount old status file(s)");
    }
}

/**
 * Log message to file and stdout
 */
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message\n";

    // Write to log file
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND);

    // Also output to stdout for cron emails
    echo $logEntry;
}
