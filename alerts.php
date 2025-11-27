<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'database.php';

// Get unscanned files as potential threats
$threats = [];
$result = $conn->query("SELECT o.id, o.file_key, o.file_size, o.uploaded_at, u.username, b.name as bucket_name, o.mime_type
    FROM objects o
    LEFT JOIN users u ON o.uploaded_by = u.id
    LEFT JOIN buckets b ON o.bucket_id = b.id
    WHERE o.is_scanned = FALSE
    ORDER BY o.uploaded_at DESC");
if ($result) {
    $threats = $result->fetch_all(MYSQLI_ASSOC);
}

// Get statistics
$high_severity = count($threats); // unscanned files
$medium_severity = $conn->query("SELECT COUNT(*) as count FROM objects WHERE file_size > (1024*1024*10)")->fetch_assoc()['count']; // Files > 10MB
$low_severity = $conn->query("SELECT COUNT(*) as count FROM audit_logs WHERE action = 'DELETE'")->fetch_assoc()['count']; // Deletions
$total_alerts = $high_severity + $medium_severity + $low_severity;

// Get suspicious file types
$suspicious_types = [];
$result = $conn->query("SELECT mime_type, COUNT(*) as count FROM objects WHERE is_scanned = FALSE GROUP BY mime_type ORDER BY count DESC LIMIT 10");
if ($result) {
    $suspicious_types = $result->fetch_all(MYSQLI_ASSOC);
}

// Get recent audit events that might be suspicious
$suspicious_events = [];
$result = $conn->query("SELECT al.id, al.action, al.logged_at, u.username, al.ip_address, o.file_key
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    LEFT JOIN objects o ON al.object_id = o.id
    WHERE al.action IN ('DELETE', 'DOWNLOAD')
    ORDER BY al.logged_at DESC
    LIMIT 20");
if ($result) {
    $suspicious_events = $result->fetch_all(MYSQLI_ASSOC);
}

function getSeverityLevel($size) {
    if ($size > 100 * 1024 * 1024) return 'high'; // > 100MB
    if ($size > 50 * 1024 * 1024) return 'medium'; // > 50MB
    return 'low';
}

function getSeverityColor($level) {
    $colors = [
        'high' => '#ff6b6b',
        'medium' => '#ffd43b',
        'low' => '#51cf66'
    ];
    return $colors[$level] ?? '#999';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DLP - Threat Alerts</title>

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

    .alert-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }

    .alert-card {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0px 2px 8px rgba(0,0,0,0.08);
        border-left: 4px solid;
    }

    .alert-card.high {
        border-left-color: #ff6b6b;
    }

    .alert-card.medium {
        border-left-color: #ffd43b;
    }

    .alert-card.low {
        border-left-color: #51cf66;
    }

    .alert-card h4 {
        margin: 0;
        color: #666;
        font-size: 12px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .alert-card .value {
        margin: 10px 0 0 0;
        font-size: 32px;
        font-weight: 700;
        color: #1a1a1a;
    }

    .alert-card .subtext {
        margin: 5px 0 0 0;
        color: #999;
        font-size: 12px;
    }

    .alert-box {
        background: #ffe3e3;
        border-left: 4px solid #ff6b6b;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        color: #c92a2a;
    }

    .alert-box strong {
        display: block;
        margin-bottom: 5px;
    }

    .alert-box.medium {
        background: #fff3bf;
        border-left-color: #ffd43b;
        color: #92400e;
    }

    .alert-box.low {
        background: #d0ebff;
        border-left-color: #4dabf7;
        color: #1971c2;
    }

    .threat-list {
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0px 2px 8px rgba(0,0,0,0.08);
    }

    .threat-item {
        padding: 20px;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background 0.2s ease;
    }

    .threat-item:hover {
        background: #f9f9f9;
    }

    .threat-item:last-child {
        border-bottom: none;
    }

    .threat-info h4 {
        margin: 0 0 5px 0;
        color: #1a1a1a;
        font-size: 14px;
    }

    .threat-info p {
        margin: 0;
        color: #666;
        font-size: 12px;
    }

    .threat-severity {
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .threat-severity.high {
        background: #ffe3e3;
        color: #c92a2a;
    }

    .threat-severity.medium {
        background: #fff3bf;
        color: #92400e;
    }

    .threat-severity.low {
        background: #d0ebff;
        color: #1971c2;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0px 2px 8px rgba(0,0,0,0.08);
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

    .action-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .action-delete {
        background: #ffe3e3;
        color: #c92a2a;
    }

    .action-download {
        background: #d0ebff;
        color: #1971c2;
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #999;
    }

    .empty-state-icon {
        font-size: 48px;
        margin-bottom: 10px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0px 2px 8px rgba(0,0,0,0.08);
        border-left: 4px solid #7a0010;
    }

    .stat-card h4 {
        margin: 0;
        color: #666;
        font-size: 12px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-card .value {
        margin: 10px 0 0 0;
        font-size: 28px;
        font-weight: 700;
        color: #1a1a1a;
    }

    .stat-card .subtext {
        margin: 5px 0 0 0;
        color: #999;
        font-size: 12px;
    }

</style>

</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">

    <div class="header">
        <div>
            <h2>Threat Alerts</h2>
            <p>Security alerts and suspicious activity monitoring</p>
        </div>
    </div>

    <!-- Alert Summary -->
    <div class="alert-summary">
        <div class="alert-card high">
            <h4>🔴 High Severity</h4>
            <div class="value"><?php echo $high_severity; ?></div>
            <div class="subtext">unscanned files</div>
        </div>

        <div class="alert-card medium">
            <h4>🟡 Medium Severity</h4>
            <div class="value"><?php echo $medium_severity; ?></div>
            <div class="subtext">large files</div>
        </div>

        <div class="alert-card low">
            <h4>🟢 Low Severity</h4>
            <div class="value"><?php echo $low_severity; ?></div>
            <div class="subtext">deletion events</div>
        </div>

        <div class="alert-card">
            <h4>📊 Total Alerts</h4>
            <div class="value"><?php echo $total_alerts; ?></div>
            <div class="subtext">all severity levels</div>
        </div>
    </div>

    <!-- System Status -->
    <?php if ($high_severity === 0): ?>
        <div class="alert-box low">
            <strong>✅ All Systems Secure</strong>
            No pending threats detected. All files have been scanned.
        </div>
    <?php elseif ($high_severity > 0): ?>
        <div class="alert-box">
            <strong>⚠️ High Priority Alert</strong>
            <?php echo $high_severity; ?> files pending security scan. Review and verify immediately.
        </div>
    <?php endif; ?>

    <!-- Unscanned Files (High Priority Threats) -->
    <?php if (count($threats) > 0): ?>
    <div class="section-title">🔍 Pending Security Scans (High Priority)</div>
    <div class="threat-list">
        <?php foreach (array_slice($threats, 0, 10) as $threat): ?>
            <div class="threat-item">
                <div class="threat-info">
                    <h4>📄 <?php echo htmlspecialchars($threat['file_key']); ?></h4>
                    <p>
                        Bucket: <strong><?php echo htmlspecialchars($threat['bucket_name'] ?? 'Unknown'); ?></strong> | 
                        Size: <strong><?php echo round($threat['file_size'] / 1024, 2); ?> KB</strong> | 
                        Uploaded: <strong><?php echo date('M d, Y H:i', strtotime($threat['uploaded_at'])); ?></strong>
                    </p>
                </div>
                <div class="threat-severity high">Unscanned</div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Suspicious File Types -->
    <?php if (count($suspicious_types) > 0): ?>
    <div class="section-title">📋 Suspicious File Types (Unscanned)</div>
    <div class="stats-grid">
        <?php foreach ($suspicious_types as $type): ?>
            <div class="stat-card">
                <h4><?php echo htmlspecialchars($type['mime_type'] ?? 'Unknown'); ?></h4>
                <div class="value"><?php echo $type['count']; ?></div>
                <div class="subtext">unscanned files</div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Recent Suspicious Events -->
    <div class="section-title">⚠️ Recent Suspicious Events</div>
    <?php if (count($suspicious_events) > 0): ?>
    <table>
        <thead>
            <tr>
                <th>User</th>
                <th>Action</th>
                <th>File</th>
                <th>IP Address</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($suspicious_events as $event): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($event['username'] ?? 'System'); ?></strong></td>
                    <td>
                        <span class="action-badge action-<?php echo strtolower($event['action']); ?>">
                            <?php echo htmlspecialchars($event['action']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($event['file_key'] ?? 'Unknown'); ?></td>
                    <td><code><?php echo htmlspecialchars($event['ip_address'] ?? 'N/A'); ?></code></td>
                    <td><?php echo date('M d, Y H:i:s', strtotime($event['logged_at'])); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>User</th>
                <th>Action</th>
                <th>File</th>
                <th>IP Address</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="5">
                    <div class="empty-state">
                        <div class="empty-state-icon">✅</div>
                        <p>No suspicious events recorded</p>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
    <?php endif; ?>

</div>

</body>
</html>
