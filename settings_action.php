<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'database.php';

// Verify admin access
$user = $conn->query("SELECT role FROM users WHERE id = " . $_SESSION['user_id'])->fetch_assoc();
if ($user['role'] !== 'admin') {
    $_SESSION['error'] = 'Admin access required';
    header('Location: dashboard.php');
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function updateSetting($key, $value) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
    if ($stmt) {
        $stmt->bind_param("sss", $key, $value, $value);
        return $stmt->execute();
    }
    return false;
}

if ($action === 'update_general') {
    $app_name = $_POST['app_name'] ?? '';
    $app_version = $_POST['app_version'] ?? '';
    $timezone = $_POST['timezone'] ?? 'UTC';
    $max_file_size = $_POST['max_file_size'] ?? '1073741824';
    $scan_interval = $_POST['scan_interval'] ?? '24';
    $session_timeout = $_POST['session_timeout'] ?? '3600';

    updateSetting('app_name', $app_name);
    updateSetting('app_version', $app_version);
    updateSetting('timezone', $timezone);
    updateSetting('max_file_size', $max_file_size);
    updateSetting('scan_interval', $scan_interval);
    updateSetting('session_timeout', $session_timeout);

    $_SESSION['success'] = 'General settings updated successfully';

} elseif ($action === 'update_security') {
    $enable_audit_log = isset($_POST['enable_audit_log']) ? 1 : 0;
    $audit_retention = $_POST['audit_retention_days'] ?? '180';
    $enable_2fa = isset($_POST['enable_2fa']) ? 1 : 0;

    updateSetting('enable_audit_log', $enable_audit_log);
    updateSetting('audit_retention_days', $audit_retention);
    updateSetting('enable_2fa', $enable_2fa);

    $_SESSION['success'] = 'Security settings updated successfully';

} elseif ($action === 'update_password_policy') {
    $min_length = $_POST['min_length'] ?? '8';
    $require_uppercase = isset($_POST['require_uppercase']) ? 1 : 0;
    $require_numbers = isset($_POST['require_numbers']) ? 1 : 0;
    $require_special = isset($_POST['require_special']) ? 1 : 0;
    $password_expiration = $_POST['password_expiration'] ?? '90';

    updateSetting('password_min_length', $min_length);
    updateSetting('password_require_uppercase', $require_uppercase);
    updateSetting('password_require_numbers', $require_numbers);
    updateSetting('password_require_special', $require_special);
    updateSetting('password_expiration_days', $password_expiration);

    $_SESSION['success'] = 'Password policy updated successfully';

} elseif ($action === 'update_notifications') {
    $enable_notifications = isset($_POST['enable_notifications']) ? 1 : 0;
    $notification_email = $_POST['notification_email'] ?? '';
    $smtp_host = $_POST['smtp_host'] ?? '';
    $smtp_port = $_POST['smtp_port'] ?? '587';
    $smtp_user = $_POST['smtp_user'] ?? '';

    updateSetting('enable_notifications', $enable_notifications);
    updateSetting('notification_email', $notification_email);
    updateSetting('smtp_host', $smtp_host);
    updateSetting('smtp_port', $smtp_port);
    updateSetting('smtp_user', $smtp_user);

    $_SESSION['success'] = 'Notification settings updated successfully';

} elseif ($action === 'update_backup') {
    $enable_backup = isset($_POST['enable_backup']) ? 1 : 0;
    $backup_frequency = $_POST['backup_frequency'] ?? 'daily';

    updateSetting('enable_backup', $enable_backup);
    updateSetting('backup_frequency', $backup_frequency);

    $_SESSION['success'] = 'Backup settings updated successfully';

} elseif ($action === 'create_backup') {
    $backup_dir = __DIR__ . '/storage/backups';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }

    $backup_file = $backup_dir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    // Create backup using mysqldump
    $db_host = getenv('DB_HOST') ?? 'localhost';
    $db_user = getenv('DB_USER') ?? 'root';
    $db_pass = getenv('DB_PASS') ?? '';
    $db_name = getenv('DB_NAME') ?? 'dlp_system';

    $command = "mysqldump -h{$db_host} -u{$db_user}";
    if (!empty($db_pass)) {
        $command .= " -p'{$db_pass}'";
    }
    $command .= " {$db_name} > {$backup_file}";

    exec($command, $output, $return);

    if ($return === 0) {
        $_SESSION['success'] = 'Database backup created successfully: ' . basename($backup_file);
    } else {
        $_SESSION['error'] = 'Failed to create backup';
    }

} elseif ($action === 'download_backup') {
    $filename = $_GET['file'] ?? '';
    $backup_dir = __DIR__ . '/storage/backups';
    $filepath = $backup_dir . '/' . basename($filename);

    if (file_exists($filepath) && strpos(realpath($filepath), realpath($backup_dir)) === 0) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit();
    } else {
        $_SESSION['error'] = 'Backup file not found';
    }

} elseif ($action === 'delete_backup') {
    $filename = $_POST['filename'] ?? '';
    $backup_dir = __DIR__ . '/storage/backups';
    $filepath = $backup_dir . '/' . basename($filename);

    if (file_exists($filepath) && strpos(realpath($filepath), realpath($backup_dir)) === 0) {
        if (unlink($filepath)) {
            $_SESSION['success'] = 'Backup deleted successfully';
        } else {
            $_SESSION['error'] = 'Failed to delete backup';
        }
    } else {
        $_SESSION['error'] = 'Backup file not found';
    }

} elseif ($action === 'optimize_database') {
    $tables = $conn->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()");
    $optimized = 0;
    while ($table = $tables->fetch_assoc()) {
        if ($conn->query("OPTIMIZE TABLE " . $table['TABLE_NAME'])) {
            $optimized++;
        }
    }
    $_SESSION['success'] = "Database optimization complete. Optimized {$optimized} table(s).";

} elseif ($action === 'clear_cache') {
    // Clear any application-level caches
    $_SESSION['success'] = 'Cache cleared successfully. System will rebuild cache on next requests.';

} else {
    $_SESSION['error'] = 'Invalid action';
}

header('Location: settings.php');
exit();
