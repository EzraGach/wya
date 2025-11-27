<?php
/**
 * DLP System Setup Script
 * Run this from CLI: php setup.php
 */

echo "=========================================\n";
echo "DLP System Setup\n";
echo "=========================================\n\n";

// Create .env if it doesn't exist
if (!file_exists('.env')) {
    echo "Creating .env from .env.example...\n";
    if (file_exists('.env.example')) {
        copy('.env.example', '.env');
        echo "✓ Created .env - Please update it with your database credentials\n\n";
    }
}

// Load environment
$env_file = '.env';
if (file_exists($env_file)) {
    $lines = file($env_file);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            putenv(trim($line));
        }
    }
}

// Get database credentials
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';
$db_name = getenv('DB_NAME') ?: 'dlp_db';

// Test database connection
echo "Checking database connection to $db_host...\n";
$test_conn = @new mysqli($db_host, $db_user, $db_pass, '');

if ($test_conn->connect_error) {
    echo "✗ Database connection failed: " . $test_conn->connect_error . "\n";
    echo "Please check your .env file and ensure MySQL is running.\n";
    exit(1);
}
echo "✓ Database server is accessible\n\n";

// Create database if not exists
echo "Creating database '$db_name' if it doesn't exist...\n";
$result = $test_conn->query("CREATE DATABASE IF NOT EXISTS `$db_name`");
if ($result) {
    echo "✓ Database ready\n\n";
} else {
    echo "✗ Could not create database: " . $test_conn->error . "\n";
    exit(1);
}

// Switch to DLP database
$test_conn->select_db($db_name);

// Import schema
echo "Importing database schema...\n";
$schema_file = 'schema.sql';
if (file_exists($schema_file)) {
    $schema = file_get_contents($schema_file);
    $queries = array_filter(array_map('trim', explode(';', $schema)));
    
    $errors = false;
    foreach ($queries as $query) {
        if (!empty($query)) {
            if (!$test_conn->query($query)) {
                echo "✗ Schema import error: " . $test_conn->error . "\n";
                $errors = true;
            }
        }
    }
    
    if (!$errors) {
        echo "✓ Database schema imported\n\n";
    } else {
        echo "⚠ Some queries had errors (this may be normal)\n\n";
    }
} else {
    echo "✗ schema.sql not found\n";
    exit(1);
}

// Create directories
echo "Creating application directories...\n";
$dirs = ['storage/files', 'logs', 'tmp'];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "  ✓ Created $dir/\n";
    } else {
        echo "  ✓ $dir/ already exists\n";
    }
}
echo "\n";

// Hash and update admin password
echo "Setting up admin user with password 'admin123'...\n";
$hash = password_hash('admin123', PASSWORD_BCRYPT);
$update_query = "UPDATE users SET password = '$hash' WHERE username = 'admin'";
$test_conn->query($update_query);
echo "✓ Admin user configured (password: admin123)\n\n";

$test_conn->close();

echo "=========================================\n";
echo "Setup Complete!\n";
echo "=========================================\n\n";
echo "Next steps:\n";
echo "1. Update .env with your actual database credentials if needed\n";
echo "2. Start your web server: php -S localhost:8000\n";
echo "3. Open http://localhost:8000 in your browser\n";
echo "4. Login with username 'admin' and password 'admin123'\n\n";
echo "IMPORTANT:\n";
echo "- Change the admin password after first login!\n";
echo "- Keep .env secure and never commit it to git\n";
echo "- Update Tebi credentials in .env if using cloud storage\n\n";
?>
