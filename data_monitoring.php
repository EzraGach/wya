<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
include 'database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DLP - Data Monitoring</title>

<style>
    body { margin: 0; font-family: Poppins,sans-serif; background:#f5f6fa; display:flex; }

    /* Reusing sidebar */
    .sidebar {
        width: 280px; background:#7a0010; height:100vh; padding-top:20px; color:white; position:fixed;
    }
    .sidebar h2 { text-align:center; margin-bottom:20px; }
    .sidebar ul { list-style:none; }
    .sidebar ul li { padding:14px 20px; border-bottom:1px solid rgba(255,255,255,0.1); cursor:pointer; }
    .sidebar ul li:hover { background:#5b000b; }

    .main {
        margin-left:280px; padding:20px; width:calc(100% - 280px);
    }

    .header { display:flex; justify-content:space-between; }
    
    .card {
        background:white; padding:20px; margin-bottom:20px; border-radius:10px;
        box-shadow:0px 2px 6px rgba(0,0,0,0.1);
    }

    table {
        width:100%; border-collapse:collapse; background:white;
        border-radius:10px; overflow:hidden; box-shadow:0px 2px 6px rgba(0,0,0,0.1);
    }
    th,td { padding:12px; border-bottom:1px solid #eee; }
    th { background:#fafafa; color:#7a0010; }

</style>

</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">

    <div class="header">
        <h2>Data Monitoring</h2>
        <p>Real-time file & cloud activity</p>
    </div>

    <div class="card">
        <h3 style="color:#7a0010;">Live Activity</h3>
        <p>No suspicious activities detected.</p>
    </div>

    <h3 style="color:#7a0010; margin-top:20px;">File Movement Logs</h3>

    <table>
        <tr>
            <th>File</th>
            <th>User</th>
            <th>Action</th>
            <th>Location</th>
            <th>Status</th>
        </tr>
        <tr>
            <td>No records found</td>
            <td>-</td>
            <td>-</td>
            <td>-</td>
            <td>-</td>
        </tr>
    </table>

</div>

</body>
</html>
