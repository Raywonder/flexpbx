<?php
/**
 * Install SMS Providers Database Schema
 * Run this once to create all necessary tables
 */

// Load configuration
$config = require __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "Connected to database successfully\n\n";

    // Read schema file
    $schemaFile = __DIR__ . '/database/sms_providers_schema.sql';

    if (!file_exists($schemaFile)) {
        die("Error: Schema file not found at $schemaFile\n");
    }

    $sql = file_get_contents($schemaFile);

    // Split into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );

    $success = 0;
    $errors = 0;

    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
            $success++;

            // Extract table name for reporting
            if (preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches)) {
                echo "✓ Created table: {$matches[1]}\n";
            } elseif (preg_match('/INSERT INTO.*?`?(\w+)`?/i', $statement, $matches)) {
                echo "✓ Inserted data into: {$matches[1]}\n";
            }
        } catch (PDOException $e) {
            $errors++;
            // Only show error if it's not "table already exists"
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo "✗ Error: " . $e->getMessage() . "\n";
            } else {
                $success++;
                if (preg_match('/Table.*?\'(\w+)\'/', $e->getMessage(), $matches)) {
                    echo "✓ Table already exists: {$matches[1]}\n";
                }
            }
        }
    }

    echo "\n====================\n";
    echo "Installation complete!\n";
    echo "Successful: $success\n";
    echo "Errors: $errors\n";
    echo "====================\n\n";

    // Verify tables were created
    $tables = [
        'sms_providers',
        'sms_messages',
        'call_logs',
        'provider_phone_numbers',
        'sms_provider_config',
        'sms_usage_statistics',
        'webhook_logs'
    ];

    echo "Verifying tables...\n";
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✓ $table exists\n";
        } else {
            echo "✗ $table NOT FOUND\n";
        }
    }

    echo "\nDatabase setup complete!\n";

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}
