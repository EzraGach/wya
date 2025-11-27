<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
.sidebar {
    width: 280px;
    background: linear-gradient(180deg, #1a0a15 0%, #2d1320 50%, #1a0a15 100%);
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    color: white;
    display: flex;
    flex-direction: column;
    box-shadow: 8px 0 24px rgba(0, 0, 0, 0.4);
    z-index: 1000;
    overflow-y: auto;
    font-family: "Poppins", "Segoe UI", sans-serif;
}

.sidebar-header {
    padding: 28px 20px;
    border-bottom: 2px solid rgba(255, 90, 90, 0.3);
    background: rgba(0, 0, 0, 0.3);
    flex-shrink: 0;
}

.sidebar-header h2 {
    margin: 0;
    font-size: 16px;
    font-weight: 800;
    letter-spacing: 1px;
    text-align: center;
    background: linear-gradient(135deg, #ff6b6b, #ff9494);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.sidebar-menu {
    list-style: none;
    margin: 0;
    padding: 12px 0;
    flex: 1;
    overflow-y: auto;
}

.sidebar-menu li {
    padding: 13px 16px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: all 0.25s ease;
    margin: 4px 8px;
    border-radius: 8px;
    font-size: 13.5px;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.68);
    border-left: 3px solid transparent;
    position: relative;
}

.sidebar-menu li:hover {
    background: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.95);
    border-left-color: rgba(255, 107, 107, 0.5);
    transform: translateX(2px);
}

.sidebar-menu li.active {
    background: linear-gradient(90deg, rgba(255, 107, 107, 0.2), rgba(255, 107, 107, 0.05));
    color: #ff9494;
    border-left-color: #ff6b6b;
    font-weight: 600;
    box-shadow: inset 0 0 10px rgba(255, 107, 107, 0.1);
}

.sidebar-menu li .icon {
    font-size: 17px;
    min-width: 20px;
    text-align: center;
    opacity: 0.9;
}

.sidebar-menu li.active .icon {
    opacity: 1;
    font-size: 18px;
}

.sidebar-menu::-webkit-scrollbar {
    width: 5px;
}

.sidebar-menu::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
}

.sidebar-menu::-webkit-scrollbar-thumb {
    background: rgba(255, 107, 107, 0.3);
    border-radius: 3px;
}

.sidebar-menu::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 107, 107, 0.5);
}

.sidebar-footer {
    list-style: none;
    margin: 0;
    padding: 12px 0;
    border-top: 2px solid rgba(255, 107, 107, 0.2);
    flex-shrink: 0;
}

.sidebar-footer li {
    padding: 13px 16px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: all 0.25s ease;
    margin: 4px 8px;
    border-radius: 8px;
    font-size: 13.5px;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.68);
}

.sidebar-footer li:hover {
    background: rgba(255, 68, 68, 0.15);
    color: #ff9494;
    transform: translateX(2px);
}

.sidebar-footer li .icon {
    font-size: 17px;
    min-width: 20px;
    text-align: center;
}

.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 107, 107, 0.2);
    border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 107, 107, 0.4);
}

/* Adjust main content for new sidebar width */
body:has(.sidebar) {
    --sidebar-width: 280px;
}

.main {
    margin-left: 280px !important;
    width: calc(100% - 280px) !important;
}
</style>

<div class="sidebar">
    <div class="sidebar-header">
        <h2>🛡️ DLP SYSTEM</h2>
    </div>
    <ul class="sidebar-menu">
        <li class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>" onclick="window.location='dashboard.php'">
            <span class="icon">📊</span>
            <span>Dashboard</span>
        </li>
        <li class="<?php echo $current_page === 'monitoring.php' ? 'active' : ''; ?>" onclick="window.location='monitoring.php'">
            <span class="icon">👁️</span>
            <span>Data Monitoring</span>
        </li>
        <li class="<?php echo $current_page === 'access_logs.php' ? 'active' : ''; ?>" onclick="window.location='access_logs.php'">
            <span class="icon">📜</span>
            <span>Access Logs</span>
        </li>
        <li class="<?php echo $current_page === 'alerts.php' ? 'active' : ''; ?>" onclick="window.location='alerts.php'">
            <span class="icon">⚠️</span>
            <span>Threat Alerts</span>
        </li>
        <li class="<?php echo $current_page === 'policies.php' ? 'active' : ''; ?>" onclick="window.location='policies.php'">
            <span class="icon">📋</span>
            <span>Policies</span>
        </li>
        <li class="<?php echo $current_page === 'users.php' ? 'active' : ''; ?>" onclick="window.location='users.php'">
            <span class="icon">👥</span>
            <span>User Management</span>
        </li>
        <li class="<?php echo $current_page === 'reports.php' ? 'active' : ''; ?>" onclick="window.location='reports.php'">
            <span class="icon">📈</span>
            <span>Reports</span>
        </li>
        <li class="<?php echo $current_page === 'settings.php' ? 'active' : ''; ?>" onclick="window.location='settings.php'">
            <span class="icon">⚙️</span>
            <span>Settings</span>
        </li>
        <li class="<?php echo $current_page === 'scan.php' ? 'active' : ''; ?>" onclick="window.location='scan.php'">
            <span class="icon">🔍</span>
            <span>File Scanning</span>
        </li>
    </ul>
    <ul class="sidebar-footer">
        <li class="<?php echo $current_page === 'change_password.php' ? 'active' : ''; ?>" onclick="window.location='change_password.php'">
            <span class="icon">🔐</span>
            <span>Change Password</span>
        </li>
        <li onclick="window.location='logout.php'">
            <span class="icon">🚪</span>
            <span>Logout</span>
        </li>
    </ul>
</div>
