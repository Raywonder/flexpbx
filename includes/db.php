<?php
/**
 * FlexPBX Database Connection
 * PDO-based database connection for FlexPBX application
 */

// Load configuration
$config_file = __DIR__ . '/../config/config.php';
if (file_exists($config_file)) {
    require_once $config_file;
} else {
    die("Configuration file not found");
}

// Database credentials from config
$host = $DB_HOST ?? 'localhost';
$port = $DB_PORT ?? '3306';
$dbname = $DB_NAME ?? 'flexpbxuser_flexpbx';
$username = $DB_USER ?? 'flexpbxuser_flexpbxserver';
$password = $DB_PASS ?? '';

try {
    // Create PDO connection
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];

    $db = new PDO($dsn, $username, $password, $options);

    // Also create mysqli connection for compatibility with older APIs
    $conn = new mysqli($host, $username, $password, $dbname, (int)$port);
    if ($conn->connect_error) {
        throw new Exception("MySQLi connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");

} catch (PDOException $e) {
    // Log error (don't expose details to user)
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please contact administrator.");
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please contact administrator.");
}
