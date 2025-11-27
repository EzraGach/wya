<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'database.php';

$current_page = basename($_SERVER['PHP_SELF']);

// Get statistics
$stats = [];

// Total files uploaded
$result = $conn->query("SELECT COUNT(*) as total FROM objects");
$row = $result->fetch_assoc();
$stats['total_files'] = $row['total'];

// Files scanned
$result = $conn->query("SELECT COUNT(*) as scanned FROM objects WHERE is_scanned = TRUE");
$row = $result->fetch_assoc();
$stats['scanned_files'] = $row['scanned'];

// Total file size (in MB)
$result = $conn->query("SELECT SUM(file_size) as total_size FROM objects");
$row = $result->fetch_assoc();
$stats['total_size_mb'] = round(($row['total_size'] ?? 0) / (1024 * 1024), 2);

// Security score based on scanned files
$stats['security_score'] = $stats['total_files'] > 0 ? round(($stats['scanned_files'] / $stats['total_files']) * 100) : 0;

// Recent audit logs (last 10 activities)
$audit_logs = [];
$result = $conn->query("SELECT al.id, al.action, al.logged_at, u.username, o.file_key 
    FROM audit_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    LEFT JOIN objects o ON al.object_id = o.id 
    ORDER BY al.logged_at DESC 
    LIMIT 10");
if ($result) {
    $audit_logs = $result->fetch_all(MYSQLI_ASSOC);
}

// Get buckets for quick stats
$buckets = getAllBuckets();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DLP System Dashboard</title>
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

    /* Sidebar styles are in sidebar.php component */

    /* Content Area */
    .main {
        margin-left: 280px;
        padding: 20px;
        width: calc(100% - 280px);
    }

    .top-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 0 25px 0;
        border-bottom: 2px solid #f0f0f0;
    }

    .top-bar h2 {
        margin: 0;
        color: #1a1a1a;
    }

    .top-bar p {
        margin: 0;
        color: #666;
        font-size: 14px;
    }

    .welcome-box {
        background: linear-gradient(135deg, #7a0010 0%, #5b000b 100%);
        color: white;
        padding: 30px;
        border-radius: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        box-shadow: 0px 4px 12px rgba(0,0,0,0.1);
    }

    .welcome-box h3 {
        margin: 0 0 10px 0;
        font-size: 24px;
    }

    .welcome-box p {
        margin: 0;
        opacity: 0.9;
        font-size: 14px;
    }

    .welcome-box img {
        opacity: 0.9;
    }

    .stats-section {
        margin-bottom: 30px;
    }

    .stats-section h3 {
        color: #1a1a1a;
        margin-bottom: 15px;
        font-size: 16px;
        font-weight: 600;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
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
        font-size: 32px;
        font-weight: 700;
        color: #1a1a1a;
    }

    .stat-card .subtext {
        margin: 5px 0 0 0;
        color: #999;
        font-size: 12px;
    }

    .stat-card.threat {
        border-left-color: #ff6b6b;
    }

    .stat-card.scanned {
        border-left-color: #51cf66;
    }

    .stat-card.storage {
        border-left-color: #4dabf7;
    }

    .stat-card.score {
        border-left-color: #ffd43b;
    }

    .progress-container {
        margin-top: 10px;
    }

    .progress-bar-wrapper {
        height: 6px;
        background: #eee;
        border-radius: 3px;
        overflow: hidden;
    }

    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #7a0010, #ff6b6b);
        border-radius: 3px;
        transition: width 0.3s ease;
    }

    .activities-section {
        margin-top: 30px;
    }

    .activities-section h3 {
        color: #1a1a1a;
        margin-bottom: 15px;
        font-size: 16px;
        font-weight: 600;
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
    }

    th {
        background: #fafafa;
        color: #666;
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    td {
        color: #333;
        font-size: 13px;
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

<!-- SIDEBAR -->
<?php include 'sidebar.php'; ?>

<!-- MAIN CONTENT -->
<div class="main">

    <div class="top-bar">
        <div>
            <h2>Dashboard</h2>
            <p>System Overview & Recent Activities</p>
        </div>
        <p>Welcome back, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></p>
    </div>

    <!-- Welcome Box -->
    <div class="welcome-box">
        <div>
            <h3>Data Loss Prevention System</h3>
            <p>Monitor, detect, and prevent unauthorized data access in real-time. All files are tracked and audited.</p>
        </div>
        <img src="https://cdn-icons-png.flaticon.com/512/3209/3209265.png" width="100" alt="security">
    </div>

    <!-- Statistics Section -->
    <div class="stats-section">
        <h3>System Statistics</h3>
        <div class="stats-grid">
            
            <!-- Total Files -->
            <div class="stat-card">
                <h4>📁 Total Files</h4>
                <div class="value"><?php echo $stats['total_files']; ?></div>
                <div class="subtext">uploaded to system</div>
            </div>

            <!-- Files Scanned -->
            <div class="stat-card scanned">
                <h4>✅ Files Scanned</h4>
                <div class="value"><?php echo $stats['scanned_files']; ?></div>
                <div class="subtext">security checked</div>
            </div>

            <!-- Storage Used -->
            <div class="stat-card storage">
                <h4>💾 Storage Used</h4>
                <div class="value"><?php echo $stats['total_size_mb']; ?></div>
                <div class="subtext">MB total</div>
            </div>

            <!-- Security Score -->
            <div class="stat-card score">
                <h4>🔒 Security Score</h4>
                <div class="value"><?php echo $stats['security_score']; ?>%</div>
                <div class="progress-container">
                    <div class="progress-bar-wrapper">
                        <div class="progress-bar" style="width: <?php echo $stats['security_score']; ?>%"></div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Active Buckets -->
    <div class="stats-section">
        <h3>Active Buckets</h3>
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));">
            <?php if (count($buckets) > 0): ?>
                <?php foreach ($buckets as $bucket): ?>
                    <?php 
                        $bucket_files = $conn->query("SELECT COUNT(*) as count FROM objects WHERE bucket_id = " . intval($bucket['id']));
                        $bucket_count = $bucket_files->fetch_assoc()['count'];
                    ?>
                    <div class="stat-card" onclick="window.location='scan.php?bucket=<?php echo urlencode($bucket['name']); ?>'" style="cursor: pointer; transition: transform 0.2s ease;">
                        <h4>📦 <?php echo htmlspecialchars($bucket['name']); ?></h4>
                        <div class="value"><?php echo $bucket_count; ?></div>
                        <div class="subtext">files in bucket</div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <p>No buckets created yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="activities-section">
        <h3>Recent Activities</h3>
        <?php if (count($audit_logs) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>File Name</th>
                        <th>Action</th>
                        <th>User</th>
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
                            <td><?php echo date('M d, Y H:i', strtotime($log['logged_at'])); ?></td>
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
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="4">
                            <div class="empty-state">
                                <div class="empty-state-icon">📭</div>
                                <p>No activities recorded yet. Start uploading files to see activity logs.</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>

</body>
</html>