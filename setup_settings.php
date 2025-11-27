<?php
require_once 'database.php';

// Create system_settings table if it doesn't exist
$sql = 'CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(255) UNIQUE NOT NULL,
    value LONGTEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (setting_key)
)';

if ($conn->query($sql)) {
    echo "✓ system_settings table created/verified<br>";
} else {
    echo "✗ Error creating table: " . $conn->error . "<br>";
}

// Insert default settings if they don't exist
$defaults = [
    'app_name' => 'DLP System',
    'app_version' => '1.0.0',
    'timezone' => 'UTC',
    'scan_interval' => '24',
    'max_file_size' => '1073741824',
    'enable_notifications' => '1',
    'notification_email' => '',
    'smtp_host' => '',
    'smtp_port' => '587',
    'smtp_user' => '',
    'enable_backup' => '1',
    'backup_frequency' => 'daily',
    'enable_audit_log' => '1',
    'audit_retention_days' => '180',
    'session_timeout' => '3600',
    'enable_2fa' => '0',
    'password_min_length' => '8',
    'password_require_uppercase' => '1',
    'password_require_numbers' => '1',
    'password_require_special' => '1',
    'password_expiration_days' => '90'
];

$inserted = 0;
foreach ($defaults as $key => $value) {
    $stmt = $conn->prepare("INSERT IGNORE INTO system_settings (setting_key, value) VALUES (?, ?)");
    if ($stmt) {
        $stmt->bind_param("ss", $key, $value);
        if ($stmt->execute()) {
            $inserted++;
        }
        $stmt->close();
    }
}

echo "✓ {$inserted} default settings initialized<br><br>";
echo "Setup complete! You can now <a href='settings.php'>access the settings page</a>";

$conn->close();
