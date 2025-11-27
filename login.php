<?php
session_start();
require_once 'database.php';

$error = '';

// If already logged in, redirect to dashboard
if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Username and password are required.';
    } else {
        // Query user from database
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ? AND is_active = TRUE");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        // Verify password
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header("Location: dashboard.php");
            exit();
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DLP System – Login</title>

<style>
body {
    margin: 0;
    padding: 0;
    background: #f5f6fa;
    font-family: Poppins, sans-serif;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
}

.login-box {
    width: 380px;
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0px 3px 10px rgba(0,0,0,0.1);
}

.login-box h2 {
    text-align: center;
    color: #7a0010;
    margin-bottom: 20px;
}

.error {
    background: #f8d7da;
    color: #721c24;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 15px;
    border: 1px solid #f5c6cb;
}

input {
    width: 100%;
    padding: 12px;
    margin-bottom: 15px;
    border-radius: 6px;
    border: 1px solid #ddd;
    box-sizing: border-box;
}

button {
    width: 100%;
    padding: 13px;
    background: #7a0010;
    color: white;
    border-radius: 6px;
    font-size: 16px;
    border: none;
}

button:hover {
    background: #5b000b;
    cursor: pointer;
}
</style>

</head>
<body>

<div class="login-box">
    <h2>DLP SYSTEM LOGIN</h2>
    <?php if (!empty($error)): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <input type="text" name="username" placeholder="Username" required autofocus>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Sign In</button>
    </form>
    
    <p style="text-align: center; color: #666; font-size: 12px; margin-top: 20px;">
        Demo: username <strong>admin</strong> password <strong>admin123</strong>
    </p>
</div>

</body>
</html>
