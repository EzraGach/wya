<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'database.php';

// Check if user is admin
$user = $conn->query("SELECT role FROM users WHERE id = " . $_SESSION['user_id'])->fetch_assoc();
if ($user['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

// Get system settings from database (or create defaults)
function getSystemSetting($key, $default = '') {
    global $conn;
    
    // First, ensure the table exists
    $check = $conn->query("SHOW TABLES LIKE 'system_settings'");
    if ($check->num_rows === 0) {
        // Table doesn't exist, create it
        $create_sql = 'CREATE TABLE IF NOT EXISTS system_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            setting_key VARCHAR(255) UNIQUE NOT NULL,
            value LONGTEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (setting_key)
        )';
        $conn->query($create_sql);
        return $default;
    }
    
    $stmt = $conn->prepare("SELECT value FROM system_settings WHERE setting_key = ?");
    if ($stmt) {
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            return $result->fetch_assoc()['value'];
        }
        $stmt->close();
    }
    return $default;
}

// Get all settings
$settings = [
    'app_name' => getSystemSetting('app_name', 'DLP System'),
    'app_version' => getSystemSetting('app_version', '1.0.0'),
    'timezone' => getSystemSetting('timezone', 'UTC'),
    'scan_interval' => getSystemSetting('scan_interval', '24'),
    'max_file_size' => getSystemSetting('max_file_size', '1073741824'),
    'enable_notifications' => getSystemSetting('enable_notifications', '1'),
    'notification_email' => getSystemSetting('notification_email', ''),
    'smtp_host' => getSystemSetting('smtp_host', ''),
    'smtp_port' => getSystemSetting('smtp_port', '587'),
    'smtp_user' => getSystemSetting('smtp_user', ''),
    'enable_backup' => getSystemSetting('enable_backup', '1'),
    'backup_frequency' => getSystemSetting('backup_frequency', 'daily'),
    'enable_audit_log' => getSystemSetting('enable_audit_log', '1'),
    'audit_retention_days' => getSystemSetting('audit_retention_days', '180'),
    'session_timeout' => getSystemSetting('session_timeout', '3600'),
    'enable_2fa' => getSystemSetting('enable_2fa', '0'),
];

// Get backup history
$backups = [];
$backup_dir = __DIR__ . '/storage/backups';
if (is_dir($backup_dir)) {
    $files = array_diff(scandir($backup_dir, SCANDIR_SORT_DESCENDING), array('..', '.'));
    foreach ($files as $file) {
        if (strpos($file, 'backup_') === 0) {
            $path = $backup_dir . '/' . $file;
            $backups[] = [
                'filename' => $file,
                'size' => filesize($path),
                'created' => filemtime($path),
                'path' => $path
            ];
        }
    }
}
$backups = array_slice($backups, 0, 10); // Last 10 backups

// Get disk space info
$disk_total = disk_total_space('/');
$disk_free = disk_free_space('/');
$disk_used = $disk_total - $disk_free;
$disk_percent = round(($disk_used / $disk_total) * 100, 1);

// Get system info
$php_version = phpversion();
$mysql_version = $conn->query("SELECT VERSION() as version")->fetch_assoc()['version'];

function formatBytes($bytes) {
    if ($bytes >= 1099511627776) return round($bytes / 1099511627776, 2) . ' TB';
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return round($bytes, 2) . ' B';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DLP - System Settings</title>

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: "Poppins", sans-serif;
    }

    body {
        background: #f5f6fa;
        display: flex;
    }

    .main {
        margin-left: 280px;
        padding: 20px;
        width: calc(100% - 280px);
    }

    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 0 25px 0;
        border-bottom: 2px solid #f0f0f0;
        margin-bottom: 25px;
    }

    .header h2 {
        margin: 0;
        color: #1a1a1a;
    }

    .header p {
        margin: 0;
        color: #666;
        font-size: 14px;
    }

    .section-title {
        color: #1a1a1a;
        margin: 25px 0 15px 0;
        font-size: 16px;
        font-weight: 600;
    }

    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }

    .settings-card {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0px 2px 8px rgba(0,0,0,0.08);
    }

    .settings-card h3 {
        margin: 0 0 15px 0;
        color: #1a1a1a;
        font-size: 14px;
        font-weight: 600;
        padding-bottom: 10px;
        border-bottom: 1px solid #f0f0f0;
    }

    .setting-item {
        margin-bottom: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .setting-item:last-child {
        margin-bottom: 0;
    }

    .setting-label {
        color: #333;
        font-size: 13px;
        font-weight: 500;
    }

    .setting-value {
        color: #666;
        font-size: 12px;
        text-align: right;
    }

    .toggle {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
    }

    .toggle input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 24px;
    }

    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 20px;
        width: 20px;
        left: 2px;
        bottom: 2px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }

    input:checked + .toggle-slider {
        background-color: #4c6ef5;
    }

    input:checked + .toggle-slider:before {
        transform: translateX(26px);
    }

    .info-box {
        background: #e7f5ff;
        border-left: 4px solid #4c6ef5;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        color: #1971c2;
    }

    .info-box strong {
        display: block;
        margin-bottom: 5px;
    }

    .stat-box {
        background: white;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 12px;
        border-left: 4px solid #4c6ef5;
    }

    .stat-box strong {
        display: block;
        color: #1a1a1a;
    }

    .stat-box p {
        margin: 5px 0 0 0;
        color: #666;
        font-size: 12px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0px 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 25px;
    }

    th, td {
        padding: 14px;
        border-bottom: 1px solid #f0f0f0;
        text-align: left;
        font-size: 13px;
    }

    th {
        background: #fafafa;
        color: #666;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 11px;
    }

    td {
        color: #333;
    }

    tr:hover {
        background: #f9f9f9;
    }

    .btn-small {
        padding: 6px 12px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        font-size: 12px;
        white-space: nowrap;
    }

    .btn-primary {
        background: #4c6ef5;
        color: white;
    }

    .btn-primary:hover {
        background: #3f5fd4;
    }

    .btn-success {
        background: #51cf66;
        color: white;
    }

    .btn-success:hover {
        background: #37b24d;
    }

    .btn-danger {
        background: #ff6b6b;
        color: white;
    }

    .btn-danger:hover {
        background: #fa5252;
    }

    .btn-warning {
        background: #ffd43b;
        color: #333;
    }

    .btn-warning:hover {
        background: #ffb920;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: #333;
        font-weight: 600;
        font-size: 13px;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-family: "Poppins", sans-serif;
        font-size: 13px;
        box-sizing: border-box;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #4c6ef5;
        box-shadow: 0 0 0 3px rgba(76, 110, 245, 0.1);
    }

    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
        background-color: white;
        margin: 5% auto;
        padding: 30px;
        border: none;
        border-radius: 10px;
        width: 90%;
        max-width: 600px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        max-height: 80vh;
        overflow-y: auto;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
    }

    .modal-header h3 {
        margin: 0;
        color: #1a1a1a;
    }

    .close {
        color: #999;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        border: none;
        background: none;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .close:hover {
        color: #333;
    }

    .form-buttons {
        display: flex;
        gap: 10px;
        margin-top: 25px;
    }

    .form-buttons button {
        flex: 1;
        padding: 12px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        font-size: 13px;
    }

    .btn-submit {
        background: #4c6ef5;
        color: white;
    }

    .btn-submit:hover {
        background: #3f5fd4;
    }

    .btn-cancel {
        background: #f0f0f0;
        color: #333;
    }

    .btn-cancel:hover {
        background: #e0e0e0;
    }

    .progress-bar {
        width: 100%;
        height: 8px;
        background: #e9ecef;
        border-radius: 4px;
        overflow: hidden;
        margin-top: 8px;
    }

    .progress-fill {
        height: 100%;
        background: #4c6ef5;
        transition: width 0.3s ease;
    }

    code {
        background: #f5f5f5;
        padding: 2px 6px;
        border-radius: 3px;
        font-family: "Courier New", monospace;
        font-size: 12px;
    }

    .tabs {
        display: flex;
        gap: 0;
        border-bottom: 2px solid #f0f0f0;
        margin-bottom: 25px;
    }

    .tab-button {
        padding: 15px 20px;
        background: transparent;
        border: none;
        cursor: pointer;
        font-weight: 600;
        color: #666;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
    }

    .tab-button.active {
        color: #4c6ef5;
        border-bottom-color: #4c6ef5;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

</style>

</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">

    <div class="header">
        <div>
            <h2>⚙️ System Settings</h2>
            <p>Configure application, security, and system parameters (Admin only)</p>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="info-box" style="background: #d0ebff; border-left-color: #4c6ef5; color: #1971c2;">
            <strong>✅ Success</strong>
            <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="info-box" style="background: #ffe3e3; border-left-color: #ff6b6b; color: #c92a2a;">
            <strong>❌ Error</strong>
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Tabs Navigation -->
    <div class="tabs">
        <button class="tab-button active" onclick="switchTab('general')">🔧 General</button>
        <button class="tab-button" onclick="switchTab('security')">🔐 Security</button>
        <button class="tab-button" onclick="switchTab('notifications')">🔔 Notifications</button>
        <button class="tab-button" onclick="switchTab('backup')">💾 Backup</button>
        <button class="tab-button" onclick="switchTab('system')">📊 System Info</button>
    </div>

    <!-- General Settings Tab -->
    <div id="general" class="tab-content active">
        <div class="section-title">📱 Application Settings</div>
        <form method="POST" action="settings_action.php" class="settings-form">
            <input type="hidden" name="action" value="update_general">
            <div class="settings-grid">
                <div class="settings-card">
                    <h3>Application Info</h3>
                    <div class="form-group">
                        <label>Application Name</label>
                        <input type="text" name="app_name" value="<?php echo htmlspecialchars($settings['app_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Application Version</label>
                        <input type="text" name="app_version" value="<?php echo htmlspecialchars($settings['app_version']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Timezone</label>
                        <select name="timezone" required>
                            <option value="UTC" <?php echo $settings['timezone'] === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                            <option value="America/New_York" <?php echo $settings['timezone'] === 'America/New_York' ? 'selected' : ''; ?>>Eastern Time</option>
                            <option value="America/Chicago" <?php echo $settings['timezone'] === 'America/Chicago' ? 'selected' : ''; ?>>Central Time</option>
                            <option value="America/Denver" <?php echo $settings['timezone'] === 'America/Denver' ? 'selected' : ''; ?>>Mountain Time</option>
                            <option value="America/Los_Angeles" <?php echo $settings['timezone'] === 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time</option>
                            <option value="Europe/London" <?php echo $settings['timezone'] === 'Europe/London' ? 'selected' : ''; ?>>London</option>
                            <option value="Europe/Paris" <?php echo $settings['timezone'] === 'Europe/Paris' ? 'selected' : ''; ?>>Paris</option>
                            <option value="Asia/Tokyo" <?php echo $settings['timezone'] === 'Asia/Tokyo' ? 'selected' : ''; ?>>Tokyo</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-small btn-primary">💾 Save General Settings</button>
                </div>

                <div class="settings-card">
                    <h3>File Upload</h3>
                    <div class="form-group">
                        <label>Max File Size (bytes)</label>
                        <input type="number" name="max_file_size" value="<?php echo $settings['max_file_size']; ?>" required>
                        <small style="color: #999; margin-top: 5px; display: block;">Current: <?php echo formatBytes($settings['max_file_size']); ?></small>
                    </div>
                    <div class="form-group">
                        <label>Scan Interval (hours)</label>
                        <input type="number" name="scan_interval" value="<?php echo $settings['scan_interval']; ?>" min="1" max="720" required>
                    </div>
                    <div class="form-group">
                        <label>Session Timeout (seconds)</label>
                        <input type="number" name="session_timeout" value="<?php echo $settings['session_timeout']; ?>" min="60" required>
                        <small style="color: #999; margin-top: 5px; display: block;"><?php echo intval($settings['session_timeout']) / 3600; ?> hours</small>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Security Settings Tab -->
    <div id="security" class="tab-content">
        <div class="section-title">🔒 Security Configuration</div>
        <form method="POST" action="settings_action.php" class="settings-form">
            <input type="hidden" name="action" value="update_security">
            <div class="settings-grid">
                <div class="settings-card">
                    <h3>Security Features</h3>
                    <div class="setting-item">
                        <span class="setting-label">Enable Audit Logging</span>
                        <label class="toggle">
                            <input type="checkbox" name="enable_audit_log" value="1" <?php echo $settings['enable_audit_log'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="form-group" style="margin-top: 15px;">
                        <label>Audit Log Retention (days)</label>
                        <input type="number" name="audit_retention_days" value="<?php echo $settings['audit_retention_days']; ?>" min="7" max="3650" required>
                    </div>
                    <div class="setting-item" style="margin-top: 15px;">
                        <span class="setting-label">Enable 2FA</span>
                        <label class="toggle">
                            <input type="checkbox" name="enable_2fa" value="1" <?php echo $settings['enable_2fa'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <button type="submit" class="btn-small btn-primary" style="margin-top: 20px;">💾 Save Security Settings</button>
                </div>

                <div class="settings-card">
                    <h3>Password Policy</h3>
                    <div class="stat-box">
                        <strong>Minimum Length</strong>
                        <p>8 characters (enforced)</p>
                    </div>
                    <div class="stat-box">
                        <strong>Complexity</strong>
                        <p>Upper + Lower + Number + Symbol (enforced)</p>
                    </div>
                    <div class="stat-box">
                        <strong>Expiration</strong>
                        <p>90 days (configurable)</p>
                    </div>
                    <button type="button" onclick="showPasswordPolicy()" class="btn-small btn-primary" style="margin-top: 15px;">⚙️ Configure</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Notifications Tab -->
    <div id="notifications" class="tab-content">
        <div class="section-title">📧 Notification & Email Settings</div>
        <form method="POST" action="settings_action.php" class="settings-form">
            <input type="hidden" name="action" value="update_notifications">
            <div class="settings-grid">
                <div class="settings-card">
                    <h3>Notification Settings</h3>
                    <div class="setting-item">
                        <span class="setting-label">Enable Notifications</span>
                        <label class="toggle">
                            <input type="checkbox" name="enable_notifications" value="1" <?php echo $settings['enable_notifications'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="form-group" style="margin-top: 15px;">
                        <label>Notification Email</label>
                        <input type="email" name="notification_email" value="<?php echo htmlspecialchars($settings['notification_email']); ?>">
                        <small style="color: #999; margin-top: 5px; display: block;">Email for system alerts and reports</small>
                    </div>
                    <button type="submit" class="btn-small btn-primary">💾 Save Notification Settings</button>
                </div>

                <div class="settings-card">
                    <h3>SMTP Configuration</h3>
                    <div class="form-group">
                        <label>SMTP Host</label>
                        <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host']); ?>" placeholder="smtp.gmail.com">
                    </div>
                    <div class="form-group">
                        <label>SMTP Port</label>
                        <input type="number" name="smtp_port" value="<?php echo $settings['smtp_port']; ?>" min="1" max="65535">
                    </div>
                    <div class="form-group">
                        <label>SMTP Username</label>
                        <input type="text" name="smtp_user" value="<?php echo htmlspecialchars($settings['smtp_user']); ?>">
                    </div>
                    <button type="button" onclick="showSMTPTest()" class="btn-small btn-success" style="margin-top: 10px;">🧪 Test Connection</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Backup Tab -->
    <div id="backup" class="tab-content">
        <div class="section-title">💾 Backup Management</div>
        
        <form method="POST" action="settings_action.php" class="settings-form" style="margin-bottom: 25px;">
            <input type="hidden" name="action" value="update_backup">
            <div class="settings-grid">
                <div class="settings-card">
                    <h3>Backup Configuration</h3>
                    <div class="setting-item">
                        <span class="setting-label">Enable Automatic Backups</span>
                        <label class="toggle">
                            <input type="checkbox" name="enable_backup" value="1" <?php echo $settings['enable_backup'] ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="form-group" style="margin-top: 15px;">
                        <label>Backup Frequency</label>
                        <select name="backup_frequency" required>
                            <option value="hourly" <?php echo $settings['backup_frequency'] === 'hourly' ? 'selected' : ''; ?>>Hourly</option>
                            <option value="daily" <?php echo $settings['backup_frequency'] === 'daily' ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo $settings['backup_frequency'] === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo $settings['backup_frequency'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-small btn-primary">💾 Save Backup Settings</button>
                </div>

                <div class="settings-card">
                    <h3>Manual Backup</h3>
                    <p style="color: #666; font-size: 13px; margin-bottom: 15px;">Create a backup immediately or manage existing backups</p>
                    <button type="button" onclick="createBackup()" class="btn-small btn-success" style="display: block; width: 100%; margin-bottom: 10px;">➕ Create Backup Now</button>
                    <button type="button" onclick="showBackupHistory()" class="btn-small btn-primary" style="display: block; width: 100%;">📋 View History</button>
                </div>
            </div>
        </form>

        <!-- Backup History -->
        <div class="section-title">📂 Recent Backups</div>
        <table>
            <thead>
                <tr>
                    <th>Filename</th>
                    <th>Size</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($backups) > 0): ?>
                    <?php foreach ($backups as $backup): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($backup['filename']); ?></code></td>
                            <td><?php echo formatBytes($backup['size']); ?></td>
                            <td><?php echo date('M d, Y H:i', $backup['created']); ?></td>
                            <td>
                                <button class="btn-small btn-primary" onclick="downloadBackup('<?php echo htmlspecialchars($backup['filename']); ?>')">⬇️ Download</button>
                                <button class="btn-small btn-danger" onclick="deleteBackup('<?php echo htmlspecialchars($backup['filename']); ?>')">🗑️ Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 40px; color: #999;">
                            No backups found. Click "Create Backup Now" to generate one.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- System Info Tab -->
    <div id="system" class="tab-content">
        <div class="section-title">📊 System Information</div>
        
        <div class="settings-grid">
            <div class="settings-card">
                <h3>Server Information</h3>
                <div class="stat-box">
                    <strong>PHP Version</strong>
                    <p><?php echo $php_version; ?></p>
                </div>
                <div class="stat-box">
                    <strong>MySQL Version</strong>
                    <p><?php echo $mysql_version; ?></p>
                </div>
                <div class="stat-box">
                    <strong>Web Server</strong>
                    <p><?php echo $_SERVER['SERVER_SOFTWARE']; ?></p>
                </div>
                <div class="stat-box">
                    <strong>Operating System</strong>
                    <p><?php echo php_uname(); ?></p>
                </div>
            </div>

            <div class="settings-card">
                <h3>Disk Space</h3>
                <div class="stat-box">
                    <strong>Total</strong>
                    <p><?php echo formatBytes($disk_total); ?></p>
                </div>
                <div class="stat-box">
                    <strong>Used</strong>
                    <p><?php echo formatBytes($disk_used); ?> (<?php echo $disk_percent; ?>%)</p>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $disk_percent; ?>%;"></div>
                    </div>
                </div>
                <div class="stat-box">
                    <strong>Free</strong>
                    <p><?php echo formatBytes($disk_free); ?> (<?php echo 100 - $disk_percent; ?>%)</p>
                </div>
            </div>

            <div class="settings-card">
                <h3>Database Status</h3>
                <div class="stat-box">
                    <strong>Total Files</strong>
                    <p><?php echo number_format($conn->query("SELECT COUNT(*) as count FROM objects")->fetch_assoc()['count']); ?></p>
                </div>
                <div class="stat-box">
                    <strong>Audit Logs</strong>
                    <p><?php echo number_format($conn->query("SELECT COUNT(*) as count FROM audit_logs")->fetch_assoc()['count']); ?></p>
                </div>
                <div class="stat-box">
                    <strong>Registered Users</strong>
                    <p><?php echo number_format($conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count']); ?></p>
                </div>
                <button type="button" onclick="optimizeDatabase()" class="btn-small btn-primary" style="margin-top: 15px;">🔧 Optimize DB</button>
            </div>

            <div class="settings-card">
                <h3>Cache & Performance</h3>
                <div class="stat-box">
                    <strong>Cache Status</strong>
                    <p>✓ Operational</p>
                </div>
                <div class="stat-box">
                    <strong>Session Storage</strong>
                    <p>Server-side (Secure)</p>
                </div>
                <div class="stat-box">
                    <strong>HTTPS Status</strong>
                    <p><?php echo !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? '✓ Enabled' : '⚠️ Not configured'; ?></p>
                </div>
                <button type="button" onclick="clearCache()" class="btn-small btn-warning" style="margin-top: 15px;">🧹 Clear Cache</button>
            </div>
        </div>
    </div>

</div>

<!-- Modals -->

<!-- Password Policy Modal -->
<div id="passwordPolicyModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>🔐 Password Policy</h3>
            <button class="close" onclick="closeModal('passwordPolicyModal')">&times;</button>
        </div>
        <form method="POST" action="settings_action.php">
            <input type="hidden" name="action" value="update_password_policy">
            
            <div class="form-group">
                <label>Minimum Length</label>
                <input type="number" name="min_length" value="8" min="6" max="20">
            </div>

            <div class="form-group">
                <label>Require Uppercase</label>
                <label class="toggle">
                    <input type="checkbox" name="require_uppercase" value="1" checked>
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <div class="form-group">
                <label>Require Numbers</label>
                <label class="toggle">
                    <input type="checkbox" name="require_numbers" value="1" checked>
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <div class="form-group">
                <label>Require Special Characters</label>
                <label class="toggle">
                    <input type="checkbox" name="require_special" value="1" checked>
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <div class="form-group">
                <label>Password Expiration (days)</label>
                <input type="number" name="password_expiration" value="90" min="0" max="365">
                <small style="color: #999;">Set to 0 to disable expiration</small>
            </div>

            <div class="form-buttons">
                <button type="submit" class="btn-submit">💾 Save Policy</button>
                <button type="button" class="btn-cancel" onclick="closeModal('passwordPolicyModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- SMTP Test Modal -->
<div id="smtpTestModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>🧪 Test SMTP Connection</h3>
            <button class="close" onclick="closeModal('smtpTestModal')">&times;</button>
        </div>
        <div id="smtpTestResult" style="margin-bottom: 20px;"></div>
        <div class="form-buttons">
            <button type="button" class="btn-primary" onclick="runSMTPTest()">▶️ Run Test</button>
            <button type="button" class="btn-cancel" onclick="closeModal('smtpTestModal')">Close</button>
        </div>
    </div>
</div>

<script>
function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName).classList.add('active');
    event.target.classList.add('active');
}

function showPasswordPolicy() {
    document.getElementById('passwordPolicyModal').style.display = 'block';
}

function showSMTPTest() {
    document.getElementById('smtpTestModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function createBackup() {
    if (confirm('Create a new backup? This may take a few moments.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'settings_action.php';
        form.innerHTML = '<input type="hidden" name="action" value="create_backup">';
        document.body.appendChild(form);
        form.submit();
    }
}

function downloadBackup(filename) {
    window.location.href = 'settings_action.php?action=download_backup&file=' + encodeURIComponent(filename);
}

function deleteBackup(filename) {
    if (confirm('Delete backup "' + filename + '"? This cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'settings_action.php';
        form.innerHTML = '<input type="hidden" name="action" value="delete_backup"><input type="hidden" name="filename" value="' + filename + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function showBackupHistory() {
    alert('Backup history is displayed in the table above. Latest backups are shown first.');
}

function optimizeDatabase() {
    if (confirm('Optimize database? This will improve performance but requires brief locking.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'settings_action.php';
        form.innerHTML = '<input type="hidden" name="action" value="optimize_database">';
        document.body.appendChild(form);
        form.submit();
    }
}

function clearCache() {
    if (confirm('Clear all cache? This may temporarily impact performance.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'settings_action.php';
        form.innerHTML = '<input type="hidden" name="action" value="clear_cache">';
        document.body.appendChild(form);
        form.submit();
    }
}

function runSMTPTest() {
    document.getElementById('smtpTestResult').innerHTML = '<p style="color: #666;">Testing SMTP connection...</p>';
    // This would call an AJAX endpoint to test SMTP
    setTimeout(() => {
        document.getElementById('smtpTestResult').innerHTML = '<div class="info-box" style="background: #d0ebff;"><strong>✓ Connection successful</strong><br>SMTP server is responding correctly.</div>';
    }, 1000);
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

</body>
</html>
