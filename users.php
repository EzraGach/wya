<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'database.php';

// Get all users with activity stats
$users = [];
$result = $conn->query("SELECT 
    u.id, 
    u.username, 
    u.email, 
    u.role, 
    u.created_at,
    COUNT(DISTINCT al.id) as activity_count,
    MAX(al.logged_at) as last_activity
    FROM users u
    LEFT JOIN audit_logs al ON u.id = al.user_id
    GROUP BY u.id
    ORDER BY u.created_at DESC");
if ($result) {
    $users = $result->fetch_all(MYSQLI_ASSOC);
}

// Get user statistics
$total_users = count($users);
$admin_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetch_assoc()['count'];
$user_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'")->fetch_assoc()['count'];

// Get access statistics
$total_actions = $conn->query("SELECT COUNT(*) as count FROM audit_logs")->fetch_assoc()['count'];
$active_users = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM audit_logs WHERE logged_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['count'];
$inactive_users = $total_users - $active_users;

// Get top users by activity
$top_users = [];
$result = $conn->query("SELECT u.id, u.username, u.role, COUNT(al.id) as action_count 
    FROM users u 
    LEFT JOIN audit_logs al ON u.id = al.user_id 
    GROUP BY u.id 
    ORDER BY action_count DESC 
    LIMIT 5");
if ($result) {
    $top_users = $result->fetch_all(MYSQLI_ASSOC);
}

function getRoleColor($role) {
    if ($role === 'admin') return '#ff6b6b';
    if ($role === 'user') return '#4c6ef5';
    return '#666';
}

function getRoleLabel($role) {
    if ($role === 'admin') return '👑 Admin';
    if ($role === 'user') return '👤 User';
    return '❓ Unknown';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DLP - User Management</title>

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

    .stat-card.admin {
        border-left-color: #ff6b6b;
    }

    .stat-card.user {
        border-left-color: #4c6ef5;
    }

    .stat-card.activity {
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

    .role-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .role-badge.admin {
        background: #ffe3e3;
        color: #c92a2a;
    }

    .role-badge.user {
        background: #d0ebff;
        color: #1971c2;
    }

    .action-buttons {
        display: flex;
        gap: 8px;
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

    .btn-edit {
        background: #4dabf7;
        color: white;
    }

    .btn-edit:hover {
        background: #339af0;
    }

    .btn-delete {
        background: #ff6b6b;
        color: white;
    }

    .btn-delete:hover {
        background: #fa5252;
    }

    .btn-primary {
        background: #4c6ef5;
        color: white;
    }

    .btn-primary:hover {
        background: #3f5fd4;
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
    .form-group select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-family: "Poppins", sans-serif;
        font-size: 13px;
        box-sizing: border-box;
    }

    .form-group input:focus,
    .form-group select:focus {
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

    .search-box {
        background: white;
        padding: 15px;
        border-radius: 10px;
        box-shadow: 0px 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 20px;
    }

    .search-input {
        width: 100%;
        padding: 10px 15px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 13px;
        font-family: "Poppins", sans-serif;
    }

    .search-input:focus {
        outline: none;
        border-color: #4c6ef5;
        box-shadow: 0 0 0 3px rgba(76, 110, 245, 0.1);
    }

</style>

</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">

    <div class="header">
        <div>
            <h2>User Management</h2>
            <p>Manage system users and access control</p>
        </div>
        <div class="header-actions">
            <button onclick="showCreateUser()" class="btn-small btn-primary">➕ Add User</button>
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

    <!-- User Statistics -->
    <div class="section-title">📊 User Statistics</div>
    <div class="stats-grid">
        <div class="stat-card">
            <h4>👥 Total Users</h4>
            <div class="value"><?php echo $total_users; ?></div>
            <div class="subtext">registered in system</div>
        </div>

        <div class="stat-card admin">
            <h4>👑 Administrators</h4>
            <div class="value"><?php echo $admin_count; ?></div>
            <div class="subtext">admin accounts</div>
        </div>

        <div class="stat-card user">
            <h4>👤 Regular Users</h4>
            <div class="value"><?php echo $user_count; ?></div>
            <div class="subtext">standard accounts</div>
        </div>

        <div class="stat-card activity">
            <h4>🟢 Active (7 days)</h4>
            <div class="value"><?php echo $active_users; ?></div>
            <div class="subtext"><?php echo $inactive_users; ?> inactive</div>
        </div>
    </div>

    <!-- Top Active Users -->
    <div class="section-title">⭐ Top Active Users</div>
    <table>
        <thead>
            <tr>
                <th>Username</th>
                <th>Role</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($top_users) > 0): ?>
                <?php foreach ($top_users as $user): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                        <td>
                            <span class="role-badge <?php echo strtolower($user['role']); ?>">
                                <?php echo getRoleLabel($user['role']); ?>
                            </span>
                        </td>
                        <td><?php echo number_format($user['action_count']); ?> actions</td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3">
                        <div class="empty-state">
                            <p>No activity data available</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Search & Filter -->
    <div class="section-title">👥 All Users</div>
    <div class="search-box">
        <input type="text" class="search-input" id="userSearch" placeholder="🔍 Search by username or email..." onkeyup="filterUsers()">
    </div>

    <!-- Users Table -->
    <table id="usersTable">
        <thead>
            <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Created</th>
                <th>Activity</th>
                <th>Last Active</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($users) > 0): ?>
                <?php foreach ($users as $user): ?>
                    <tr class="user-row">
                        <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                        <td><code><?php echo htmlspecialchars($user['email']); ?></code></td>
                        <td>
                            <span class="role-badge <?php echo strtolower($user['role']); ?>">
                                <?php echo getRoleLabel($user['role']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                        <td><?php echo number_format($user['activity_count']); ?> actions</td>
                        <td>
                            <?php if ($user['last_activity']): ?>
                                <small><?php echo date('M d, H:i', strtotime($user['last_activity'])); ?></small>
                            <?php else: ?>
                                <small style="color: #999;">Never</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-small btn-edit" onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['email']); ?>', '<?php echo $user['role']; ?>')">✏️ Edit</button>
                                <button class="btn-small btn-delete" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">🗑️ Delete</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="empty-state-icon">👥</div>
                            <p>No users found</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</div>

<!-- User Modal -->
<div id="userModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Add New User</h3>
            <button class="close" onclick="closeModal()">&times;</button>
        </div>
        <form id="userForm" method="POST" action="users_action.php">
            <input type="hidden" id="formAction" name="action" value="create">
            <input type="hidden" id="userId" name="user_id">
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required placeholder="john_doe">
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required placeholder="john@example.com">
            </div>

            <div class="form-group" id="passwordGroup">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter secure password">
            </div>

            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role" required>
                    <option value="user">👤 User</option>
                    <option value="admin">👑 Administrator</option>
                </select>
            </div>

            <div class="form-buttons">
                <button type="submit" class="btn-submit">💾 Save User</button>
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function filterUsers() {
    const searchTerm = document.getElementById('userSearch').value.toLowerCase();
    const rows = document.querySelectorAll('.user-row');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}

function showCreateUser() {
    document.getElementById('modalTitle').textContent = 'Add New User';
    document.getElementById('formAction').value = 'create';
    document.getElementById('userId').value = '';
    document.getElementById('username').value = '';
    document.getElementById('email').value = '';
    document.getElementById('password').value = '';
    document.getElementById('role').value = 'user';
    document.getElementById('passwordGroup').style.display = 'block';
    document.getElementById('password').required = true;
    document.getElementById('userModal').style.display = 'block';
}

function editUser(id, username, email, role) {
    document.getElementById('modalTitle').textContent = 'Edit User';
    document.getElementById('formAction').value = 'update';
    document.getElementById('userId').value = id;
    document.getElementById('username').value = username;
    document.getElementById('email').value = email;
    document.getElementById('role').value = role;
    document.getElementById('passwordGroup').style.display = 'none';
    document.getElementById('password').required = false;
    document.getElementById('userModal').style.display = 'block';
}

function deleteUser(id, username) {
    if (confirm('Are you sure you want to delete the user "' + username + '"? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'users_action.php';
        form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function closeModal() {
    document.getElementById('userModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('userModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

</body>
</html>
