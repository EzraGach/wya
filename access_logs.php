<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'database.php';

// Get all login/access events from audit logs
$access_logs = [];
$result = $conn->query("SELECT al.id, al.action, al.logged_at, u.username, al.ip_address, al.details
    FROM audit_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    ORDER BY al.logged_at DESC 
    LIMIT 100");
if ($result) {
    $access_logs = $result->fetch_all(MYSQLI_ASSOC);
}

// Get statistics
$total_actions = $conn->query("SELECT COUNT(*) as count FROM audit_logs")->fetch_assoc()['count'];
$unique_users = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM audit_logs")->fetch_assoc()['count'];
$unique_ips = $conn->query("SELECT COUNT(DISTINCT ip_address) as count FROM audit_logs")->fetch_assoc()['count'];

// Get most active users
$top_users = [];
$result = $conn->query("SELECT u.username, COUNT(*) as activity_count 
    FROM audit_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    GROUP BY al.user_id 
    ORDER BY activity_count DESC 
    LIMIT 5");
if ($result) {
    $top_users = $result->fetch_all(MYSQLI_ASSOC);
}

// Get action breakdown
$action_stats = [];
$result = $conn->query("SELECT action, COUNT(*) as count FROM audit_logs GROUP BY action");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $action_stats[$row['action']] = $row['count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DLP - Access Logs</title>

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
        font-size: 32px;
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

    .filter-bar {
        background: white;
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        gap: 10px;
        box-shadow: 0px 2px 8px rgba(0,0,0,0.08);
    }

    .filter-bar input,
    .filter-bar select {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 13px;
    }

    .filter-bar button {
        padding: 8px 16px;
        background: #7a0010;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
    }

    .filter-bar button:hover {
        background: #5b000b;
    }

    .ip-badge {
        background: #f0f0f0;
        padding: 2px 8px;
        border-radius: 4px;
        font-family: monospace;
        font-size: 11px;
    }

</style>

</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">

    <div class="header">
        <div>
            <h2>Access Logs</h2>
            <p>Monitor all system access and file operations</p>
        </div>
    </div>

    <!-- Statistics -->
    <div class="section-title">📊 Access Statistics</div>
    <div class="stats-grid">
        <div class="stat-card">
            <h4>📋 Total Actions</h4>
            <div class="value"><?php echo number_format($total_actions); ?></div>
            <div class="subtext">recorded events</div>
        </div>

        <div class="stat-card">
            <h4>👤 Active Users</h4>
            <div class="value"><?php echo $unique_users; ?></div>
            <div class="subtext">users in system</div>
        </div>

        <div class="stat-card">
            <h4>🌐 Unique IPs</h4>
            <div class="value"><?php echo $unique_ips; ?></div>
            <div class="subtext">accessing system</div>
        </div>
    </div>

    <!-- Top Users -->
    <?php if (count($top_users) > 0): ?>
    <div class="section-title">⭐ Most Active Users</div>
    <div class="stats-grid">
        <?php foreach ($top_users as $user): ?>
            <div class="stat-card">
                <h4>👤 <?php echo htmlspecialchars($user['username'] ?? 'Unknown'); ?></h4>
                <div class="value"><?php echo $user['activity_count']; ?></div>
                <div class="subtext">actions performed</div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Action Breakdown -->
    <?php if (count($action_stats) > 0): ?>
    <div class="section-title">📈 Action Breakdown</div>
    <div class="stats-grid">
        <?php foreach ($action_stats as $action => $count): ?>
            <div class="stat-card">
                <h4><?php echo htmlspecialchars($action); ?></h4>
                <div class="value"><?php echo $count; ?></div>
                <div class="subtext">times performed</div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Access Logs Table -->
    <div class="section-title">📜 Complete Access Log</div>
    
    <div class="filter-bar">
        <input type="text" placeholder="Search by username or IP..." id="searchInput">
        <button onclick="location.reload()">Clear Filters</button>
    </div>

    <?php if (count($access_logs) > 0): ?>
    <table>
        <thead>
            <tr>
                <th>User</th>
                <th>Action</th>
                <th>IP Address</th>
                <th>Details</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($access_logs as $log): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></strong>
                    </td>
                    <td>
                        <span class="action-badge action-<?php echo strtolower($log['action']); ?>">
                            <?php echo htmlspecialchars($log['action']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="ip-badge"><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars(substr($log['details'] ?? '', 0, 50)); ?></td>
                    <td><?php echo date('M d, Y H:i:s', strtotime($log['logged_at'])); ?></td>
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
                <th>IP Address</th>
                <th>Details</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="5">
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <p>No access logs recorded yet</p>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
    <?php endif; ?>

</div>

<script>
// Simple client-side search
document.getElementById('searchInput').addEventListener('keyup', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('table tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});
</script>

</body>
</html>
