<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'database.php';

// Get report statistics
$total_files = $conn->query("SELECT COUNT(*) as count FROM objects")->fetch_assoc()['count'];
$total_size = $conn->query("SELECT SUM(file_size) as total FROM objects")->fetch_assoc()['total'];
$scanned_files = $conn->query("SELECT COUNT(*) as count FROM objects WHERE is_scanned = TRUE")->fetch_assoc()['count'];
$scan_coverage = $total_files > 0 ? round(($scanned_files / $total_files) * 100, 1) : 0;

// Get threat statistics
$high_risk_files = $conn->query("SELECT COUNT(*) as count FROM objects WHERE is_scanned = FALSE")->fetch_assoc()['count'];
$quarantined = $conn->query("SELECT COUNT(*) as count FROM objects WHERE scan_results LIKE '%quarantine%' OR scan_results LIKE '%threat%'")->fetch_assoc()['count'];

// Get storage statistics by bucket
$storage_by_bucket = [];
$result = $conn->query("SELECT b.name, COUNT(o.id) as file_count, SUM(o.file_size) as total_size 
    FROM buckets b 
    LEFT JOIN objects o ON b.id = o.bucket_id 
    GROUP BY b.id 
    ORDER BY total_size DESC");
if ($result) {
    $storage_by_bucket = $result->fetch_all(MYSQLI_ASSOC);
}

// Get access pattern statistics (last 30 days)
$access_stats = $conn->query("SELECT 
    COUNT(*) as total_actions,
    COUNT(DISTINCT user_id) as unique_users,
    COUNT(DISTINCT ip_address) as unique_ips,
    COUNT(CASE WHEN action = 'DOWNLOAD' THEN 1 END) as downloads,
    COUNT(CASE WHEN action = 'DELETE' THEN 1 END) as deletions,
    COUNT(CASE WHEN action = 'UPLOAD' THEN 1 END) as uploads
    FROM audit_logs 
    WHERE logged_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc();

// Get file type distribution
$file_types = [];
$result = $conn->query("SELECT mime_type, COUNT(*) as count, SUM(file_size) as total_size 
    FROM objects 
    GROUP BY mime_type 
    ORDER BY count DESC 
    LIMIT 10");
if ($result) {
    $file_types = $result->fetch_all(MYSQLI_ASSOC);
}

// Get user activity report
$user_activity = [];
$result = $conn->query("SELECT 
    u.username, 
    COUNT(al.id) as action_count,
    COUNT(CASE WHEN al.action = 'DOWNLOAD' THEN 1 END) as downloads,
    COUNT(CASE WHEN al.action = 'UPLOAD' THEN 1 END) as uploads,
    COUNT(CASE WHEN al.action = 'DELETE' THEN 1 END) as deletions,
    COUNT(DISTINCT al.ip_address) as ip_count,
    MAX(al.logged_at) as last_activity
    FROM users u 
    LEFT JOIN audit_logs al ON u.id = al.user_id 
    WHERE al.logged_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY u.id 
    ORDER BY action_count DESC");
if ($result) {
    $user_activity = $result->fetch_all(MYSQLI_ASSOC);
}

function formatBytes($bytes) {
    if ($bytes === null) return '0 B';
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
<title>DLP - Reports</title>

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

    .header-actions {
        display: flex;
        gap: 10px;
    }

    .btn-small {
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        font-size: 13px;
        white-space: nowrap;
    }

    .btn-primary {
        background: #4c6ef5;
        color: white;
    }

    .btn-primary:hover {
        background: #3f5fd4;
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

    .stat-card.scan {
        border-left-color: #4c6ef5;
    }

    .stat-card.risk {
        border-left-color: #ff6b6b;
    }

    .stat-card.access {
        border-left-color: #51cf66;
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

    .action-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .action-download {
        background: #d0ebff;
        color: #1971c2;
    }

    .action-upload {
        background: #d0f9ff;
        color: #0b7285;
    }

    .action-delete {
        background: #ffe3e3;
        color: #c92a2a;
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

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #999;
    }

    .empty-state-icon {
        font-size: 48px;
        margin-bottom: 10px;
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
        max-width: 500px;
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

    .form-group {
        margin-bottom: 20px;
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

    code {
        background: #f5f5f5;
        padding: 2px 6px;
        border-radius: 3px;
        font-family: "Courier New", monospace;
        font-size: 12px;
    }

</style>

</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">

    <div class="header">
        <div>
            <h2>Reports & Analytics</h2>
            <p>DLP system reports and data analytics dashboard</p>
        </div>
        <div class="header-actions">
            <button onclick="showExportReport()" class="btn-small btn-primary">📊 Export Report</button>
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

    <!-- Executive Summary -->
    <div class="section-title">📈 Executive Summary</div>
    <div class="stats-grid">
        <div class="stat-card scan">
            <h4>📁 Total Files</h4>
            <div class="value"><?php echo number_format($total_files); ?></div>
            <div class="subtext"><?php echo formatBytes($total_size); ?> total size</div>
        </div>

        <div class="stat-card scan">
            <h4>✅ Scan Coverage</h4>
            <div class="value"><?php echo $scan_coverage; ?>%</div>
            <div class="subtext"><?php echo number_format($scanned_files); ?> scanned</div>
        </div>

        <div class="stat-card risk">
            <h4>⚠️ Unscanned Files</h4>
            <div class="value"><?php echo number_format($high_risk_files); ?></div>
            <div class="subtext">pending security review</div>
        </div>

        <div class="stat-card risk">
            <h4>🚫 Quarantined</h4>
            <div class="value"><?php echo number_format($quarantined); ?></div>
            <div class="subtext">detected threats</div>
        </div>
    </div>

    <!-- Access Activity (Last 30 Days) -->
    <div class="section-title">📊 Access Activity (Last 30 Days)</div>
    <div class="stats-grid">
        <div class="stat-card access">
            <h4>📝 Total Actions</h4>
            <div class="value"><?php echo number_format($access_stats['total_actions']); ?></div>
            <div class="subtext">recorded in audit log</div>
        </div>

        <div class="stat-card access">
            <h4>👥 Unique Users</h4>
            <div class="value"><?php echo number_format($access_stats['unique_users']); ?></div>
            <div class="subtext">active accounts</div>
        </div>

        <div class="stat-card access">
            <h4>🌐 Unique IPs</h4>
            <div class="value"><?php echo number_format($access_stats['unique_ips']); ?></div>
            <div class="subtext">access sources</div>
        </div>

        <div class="stat-card access">
            <h4>⬇️ Downloads</h4>
            <div class="value"><?php echo number_format($access_stats['downloads']); ?></div>
            <div class="subtext"><?php echo number_format($access_stats['uploads']); ?> uploads | <?php echo number_format($access_stats['deletions']); ?> deletes</div>
        </div>
    </div>

    <!-- Storage by Bucket -->
    <div class="section-title">🪣 Storage Distribution by Bucket</div>
    <table>
        <thead>
            <tr>
                <th>Bucket Name</th>
                <th>File Count</th>
                <th>Total Size</th>
                <th>Percentage</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total_bucket_size = array_sum(array_column($storage_by_bucket, 'total_size'));
            if (count($storage_by_bucket) > 0): 
            ?>
                <?php foreach ($storage_by_bucket as $bucket): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($bucket['name']); ?></strong></td>
                        <td><?php echo number_format($bucket['file_count']); ?> files</td>
                        <td><?php echo formatBytes($bucket['total_size']); ?></td>
                        <td><?php echo $total_bucket_size > 0 ? round(($bucket['total_size'] / $total_bucket_size) * 100, 1) : 0; ?>%</td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">
                        <div class="empty-state">
                            <p>No bucket data available</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- File Type Distribution -->
    <div class="section-title">📄 File Type Distribution (Top 10)</div>
    <table>
        <thead>
            <tr>
                <th>MIME Type</th>
                <th>Count</th>
                <th>Total Size</th>
                <th>Avg Size</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($file_types) > 0): ?>
                <?php foreach ($file_types as $type): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($type['mime_type']); ?></code></td>
                        <td><?php echo number_format($type['count']); ?></td>
                        <td><?php echo formatBytes($type['total_size']); ?></td>
                        <td><?php echo formatBytes($type['total_size'] / $type['count']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">
                        <div class="empty-state">
                            <p>No file type data available</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- User Activity Report -->
    <div class="section-title">👥 User Activity Report (Last 30 Days)</div>
    <table>
        <thead>
            <tr>
                <th>Username</th>
                <th>Total Actions</th>
                <th>Downloads</th>
                <th>Uploads</th>
                <th>Deletions</th>
                <th>IP Count</th>
                <th>Last Activity</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($user_activity) > 0): ?>
                <?php foreach ($user_activity as $activity): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($activity['username']); ?></strong></td>
                        <td><?php echo number_format($activity['action_count']); ?></td>
                        <td>
                            <span class="action-badge action-download">
                                <?php echo number_format($activity['downloads']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="action-badge action-upload">
                                <?php echo number_format($activity['uploads']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="action-badge action-delete">
                                <?php echo number_format($activity['deletions']); ?>
                            </span>
                        </td>
                        <td><?php echo number_format($activity['ip_count']); ?></td>
                        <td><?php echo $activity['last_activity'] ? date('M d, H:i', strtotime($activity['last_activity'])) : 'Never'; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <p>No user activity data available</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<!-- Export Report Modal -->
<div id="reportModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>📊 Export Report</h3>
            <button class="close" onclick="closeModal()">&times;</button>
        </div>
        <form id="reportForm" method="POST" action="reports_action.php">
            <input type="hidden" name="action" value="export">
            
            <div class="form-group">
                <label for="reportName">Report Name</label>
                <input type="text" id="reportName" name="report_name" required placeholder="e.g., Weekly Security Report">
            </div>

            <div class="form-group">
                <label for="reportType">Report Type</label>
                <select id="reportType" name="report_type" required>
                    <option value="">Select Type</option>
                    <option value="executive_summary">Executive Summary</option>
                    <option value="access_activity">Access Activity Report</option>
                    <option value="storage">Storage Distribution Report</option>
                    <option value="file_types">File Type Analysis</option>
                    <option value="user_activity">User Activity Report</option>
                    <option value="compliance">Compliance Report</option>
                </select>
            </div>

            <div class="form-group">
                <label for="reportFormat">Format</label>
                <select id="reportFormat" name="format" required>
                    <option value="pdf">📄 PDF</option>
                    <option value="csv">📊 CSV</option>
                    <option value="json">📋 JSON</option>
                </select>
            </div>

            <div class="form-group">
                <label for="reportNotes">Notes (Optional)</label>
                <textarea id="reportNotes" name="notes" rows="3" placeholder="Add any additional notes to the report..."></textarea>
            </div>

            <div class="form-buttons">
                <button type="submit" class="btn-submit">📥 Generate & Export</button>
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function showExportReport() {
    document.getElementById('reportName').value = '';
    document.getElementById('reportType').value = '';
    document.getElementById('reportFormat').value = 'pdf';
    document.getElementById('reportNotes').value = '';
    document.getElementById('reportModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('reportModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('reportModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

</body>
</html>
