<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'database.php';

// Get all file movements/uploads from audit logs
$audit_logs = [];
$result = $conn->query("SELECT al.id, al.action, al.logged_at, u.username, o.file_key, o.file_size, b.name as bucket_name
    FROM audit_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    LEFT JOIN objects o ON al.object_id = o.id 
    LEFT JOIN buckets b ON o.bucket_id = b.id 
    ORDER BY al.logged_at DESC");
if ($result) {
    $audit_logs = $result->fetch_all(MYSQLI_ASSOC);
}

// Get file statistics by bucket
$bucket_stats = [];
$buckets = getAllBuckets();
foreach ($buckets as $bucket) {
    $result = $conn->query("SELECT COUNT(*) as count, SUM(file_size) as total_size FROM objects WHERE bucket_id = " . intval($bucket['id']));
    $stats = $result->fetch_assoc();
    $bucket_stats[$bucket['id']] = [
        'name' => $bucket['name'],
        'file_count' => $stats['count'],
        'total_size' => $stats['total_size'] ?? 0
    ];
}

// Get recent suspicious activity (files not yet scanned)
$unscanned_files = [];
$result = $conn->query("SELECT id, file_key, file_size, uploaded_at, uploaded_by, bucket_id FROM objects WHERE is_scanned = FALSE ORDER BY uploaded_at DESC LIMIT 10");
if ($result) {
    $unscanned_files = $result->fetch_all(MYSQLI_ASSOC);
}

// Get file storage type distribution
$storage_stats = [];
$result = $conn->query("SELECT storage_type, COUNT(*) as count FROM objects GROUP BY storage_type");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $storage_stats[$row['storage_type']] = $row['count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DLP - Data Monitoring</title>

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: "Poppins", sans-serif;
    }

    body { 
        background:#f5f6fa; 
        display:flex; 
    }

    .main {
        margin-left:280px; 
        padding:20px; 
        width:calc(100% - 280px);
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

    .alert-box.warning {
        background: #fff3bf;
        border-left-color: #ffd43b;
        color: #92400e;
    }

    .alert-box.info {
        background: #d0ebff;
        border-left-color: #4dabf7;
        color: #1971c2;
    }

    table {
        width:100%; 
        border-collapse:collapse; 
        background:white;
        border-radius:10px; 
        overflow:hidden; 
        box-shadow:0px 2px 8px rgba(0,0,0,0.08);
    }

    th, td { 
        padding:14px; 
        border-bottom: 1px solid #f0f0f0; 
        text-align: left;
        font-size: 13px;
    }

    th { 
        background:#fafafa; 
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

    .action-upload {
        background: #d3f9d8;
        color: #2f9e44;
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

</style>

</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">

    <div class="header">
        <div>
            <h2>Data Monitoring</h2>
            <p>Real-time file activity and cloud storage monitoring</p>
        </div>
    </div>

    <!-- Live Activity Alert -->
    <?php if (count($unscanned_files) > 0): ?>
        <div class="alert-box warning">
            <strong>⚠️ Pending Review</strong>
            <?php echo count($unscanned_files); ?> files pending security scan
        </div>
    <?php else: ?>
        <div class="alert-box info">
            <strong>✅ All Systems Normal</strong>
            All files have been scanned and verified secure
        </div>
    <?php endif; ?>

    <!-- Bucket Statistics -->
    <div class="section-title">📊 Bucket Activity Overview</div>
    <div class="stats-grid">
        <?php if (count($bucket_stats) > 0): ?>
            <?php foreach ($bucket_stats as $bid => $bstats): ?>
                <div class="stat-card">
                    <h4>📦 <?php echo htmlspecialchars($bstats['name']); ?></h4>
                    <div class="value"><?php echo $bstats['file_count']; ?></div>
                    <div class="subtext">
                        <?php echo round($bstats['total_size'] / (1024 * 1024), 2); ?> MB total
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="stat-card">
                <h4>No buckets</h4>
                <p style="margin-top: 10px; color: #999;">Create a bucket to start monitoring</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Storage Type Distribution -->
    <div class="section-title">💾 Storage Distribution</div>
    <div class="stats-grid">
        <?php if (count($storage_stats) > 0): ?>
            <?php foreach ($storage_stats as $type => $count): ?>
                <div class="stat-card">
                    <h4><?php echo $type === 'local' ? '📁 Local Storage' : '☁️ Tebi S3'; ?></h4>
                    <div class="value"><?php echo $count; ?></div>
                    <div class="subtext">files stored</div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="stat-card">
                <h4>No files</h4>
                <p style="margin-top: 10px; color: #999;">Upload files to see storage distribution</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pending Security Scans -->
    <?php if (count($unscanned_files) > 0): ?>
    <div class="section-title">🔍 Files Pending Security Scan</div>
    <table>
        <thead>
            <tr>
                <th>File Name</th>
                <th>Size</th>
                <th>Uploaded</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($unscanned_files as $file): ?>
                <tr>
                    <td><?php echo htmlspecialchars($file['file_key']); ?></td>
                    <td><?php echo round($file['file_size'] / 1024, 2); ?> KB</td>
                    <td><?php echo date('M d, Y H:i', strtotime($file['uploaded_at'])); ?></td>
                    <td><span class="action-badge action-upload">Pending</span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- All File Movements -->
    <div class="section-title">📜 Complete File Activity Log</div>
    <?php if (count($audit_logs) > 0): ?>
    <table>
        <thead>
            <tr>
                <th>File Name</th>
                <th>Action</th>
                <th>User</th>
                <th>Bucket</th>
                <th>Size</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($audit_logs as $log): ?>
                <tr>
                    <td><?php echo htmlspecialchars($log['file_key'] ?? 'Unknown'); ?></td>
                    <td>
                        <span class="action-badge action-<?php echo strtolower($log['action']); ?>">
                            <?php echo htmlspecialchars($log['action']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></td>
                    <td><?php echo htmlspecialchars($log['bucket_name'] ?? '-'); ?></td>
                    <td><?php echo $log['file_size'] ? round($log['file_size'] / 1024, 2) . ' KB' : '-'; ?></td>
                    <td><?php echo date('M d, Y H:i:s', strtotime($log['logged_at'])); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>File Name</th>
                <th>Action</th>
                <th>User</th>
                <th>Bucket</th>
                <th>Size</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="6">
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <p>No file activities recorded yet</p>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
    <?php endif; ?>

</div>

</body>
</html>
