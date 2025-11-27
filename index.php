<?php
// index.php - DLP System Homepage
session_start();

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

// Redirect to login if not authenticated
if (!$is_logged_in) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DLP System - Dashboard</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f6fa;
        }
        .sidebar {
            width: 250px;
            height: 100vh;
            background: #600000;
            color: #fff;
            position: fixed;
            padding-top: 20px;
        }
        .sidebar h2 {
            text-align: center;
            margin-bottom: 30px;
        }
        .sidebar a {
            display: block;
            padding: 12px 20px;
            color: #fff;
            text-decoration: none;
            font-size: 15px;
        }
        .sidebar a:hover {
            background: #8b0000;
        }

        .main {
            margin-left: 260px;
            padding: 20px;
        }
        .welcome-box {
            background: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .welcome-box h1 {
            margin: 0;
            color: #333;
        }

        .section-title {
            font-size: 18px;
            margin-bottom: 10px;
            color: #600000;
            font-weight: bold;
        }

        .card-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        .card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
        }
        .card h3 {
            margin: 0 0 10px;
            color: #333;
        }

        .blue {
            background: #0077b6;
            color: #fff;
        }
        .purple {
            background: #03046e;
            color: #fff;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>DLP SYSTEM</h2>
    <a href="index.php">Dashboard</a>
    <a href="monitoring.php">Data Monitoring</a>
    <a href="policies.php">DLP Policies</a>
    <a href="alerts.php">Threat Alerts</a>
    <a href="scan.php">File Scanning</a>
    <a href="access_logs.php">Access Logs</a>
    <a href="users.php">User Management</a>
    <a href="settings.php">Settings</a>
    <?php if ($is_logged_in): ?>
    <a href="logout.php" style="background: #8b0000; margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.2);">Logout</a>
    <?php else: ?>
    <a href="login.php" style="background: #0077b6; margin-top: 20px;">Login</a>
    <?php endif; ?>
</div>

<div class="main">
    <div class="welcome-box">
        <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></h1>
        <p>Monitor sensitive data and prevent data leaks across your organization.</p>
    </div>

    <div class="section-title">System Overview</div>

    <div class="card-row">
        <div class="card blue">
            <h3>Active Incidents</h3>
            <p>0 Current Alerts</p>
        </div>

        <div class="card purple">
            <h3>Data Scanned</h3>
            <p>156 Files Today</p>
        </div>

        <div class="card blue">
            <h3>Policy Violations</h3>
            <p>3 Violations This Week</p>
        </div>
    </div>

</div>

</body>
</html>
