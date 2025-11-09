#!/usr/bin/env php
<?php
/**
 * FlexBot Background Processing
 * Runs periodically to process tasks and update training data
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/FlexBot.php';

$logFile = '/home/flexpbxuser/logs/flexbot-background.log';

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

try {
    logMessage("FlexBot background processing started");

    // Initialize FlexBot
    $flexbot_config = require __DIR__ . '/../config/flexbot-config.php';
    $flexBot = new FlexBot($pdo, $flexbot_config);

    // Check if Ollama is available
    if (!$flexBot->isAvailable()) {
        logMessage("WARNING: Ollama service not available, skipping");
        exit(0);
    }

    // Task 1: Auto-format unformatted notes
    if ($flexbot_config['auto_format_notes']) {
        logMessage("Checking for notes that need formatting...");

        $stmt = $pdo->query("
            SELECT DISTINCT ct.type_key
            FROM checklist_notes cn
            LEFT JOIN checklist_types ct ON cn.checklist_type_id = ct.id
            WHERE cn.note_content IS NOT NULL
            AND cn.note_content NOT LIKE '%<p>%'
            LIMIT 5
        ");

        $types = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($types as $typeKey) {
            logMessage("Formatting notes for checklist type: {$typeKey}");
            $result = $flexBot->updateChecklistNotes($typeKey);
            if ($result['success']) {
                logMessage("  Updated {$result['notes_updated']} of {$result['total_notes']} notes");
            }
        }
    }

    // Task 2: Process pending AI tasks
    logMessage("Checking for pending AI tasks...");

    $stmt = $pdo->query("
        SELECT * FROM flexbot_actions
        WHERE success = 0
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        LIMIT 10
    ");

    $pendingTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($pendingTasks) > 0) {
        logMessage("Found " . count($pendingTasks) . " pending tasks");

        foreach ($pendingTasks as $task) {
            // Retry failed task
            logMessage("  Retrying task ID {$task['id']}: {$task['action_type']}");
            // TODO: Implement retry logic
        }
    }

    // Task 3: Clean up old conversation history
    logMessage("Cleaning up old conversation history...");

    $cleanupStmt = $pdo->exec("
        DELETE FROM flexbot_conversations
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");

    if ($cleanupStmt > 0) {
        logMessage("  Deleted {$cleanupStmt} old conversations");
    }

    // Task 4: Update training data quality scores
    logMessage("Updating training data quality scores...");

    $trainingStmt = $pdo->query("
        SELECT id, content FROM flexbot_training_data
        WHERE quality_score = 1.0
        LIMIT 10
    ");

    $trainingData = $trainingStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($trainingData as $data) {
        // Calculate quality score based on length, formatting, etc.
        $contentLength = strlen($data['content']);
        $hasFormatting = (strpos($data['content'], '<') !== false);

        $qualityScore = 1.0;
        if ($contentLength < 50) $qualityScore -= 0.3;
        if ($contentLength > 2000) $qualityScore -= 0.1;
        if (!$hasFormatting) $qualityScore -= 0.2;

        $updateStmt = $pdo->prepare("
            UPDATE flexbot_training_data
            SET quality_score = ?
            WHERE id = ?
        ");
        $updateStmt->execute([max(0.1, $qualityScore), $data['id']]);
    }

    if (count($trainingData) > 0) {
        logMessage("  Updated quality scores for " . count($trainingData) . " training items");
    }

    // Task 5: Sync with Mastodon bot (if enabled)
    if ($flexbot_config['mastodon_bot_enabled']) {
        logMessage("Checking Mastodon bot integration...");
        // TODO: Implement Mastodon bot sync
    }

    logMessage("FlexBot background processing completed successfully");

} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    exit(1);
}
