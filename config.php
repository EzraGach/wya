<?php
/**
 * Application Configuration
 * Database credentials should be set via environment variables
 */

// Database configuration
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$dbname = getenv('DB_NAME') ?: 'dlp_db';

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Database connection failed. Please check your configuration.");
}

// Set charset
$conn->set_charset("utf8mb4");

// Application settings
define('APP_NAME', 'DLP System');
define('APP_VERSION', '1.0.0');
define('APP_DEBUG', getenv('APP_DEBUG') === 'true');
define('SESSION_TIMEOUT', 3600); // 1 hour
?>
