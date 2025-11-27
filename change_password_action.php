<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['error'] = 'All fields are required.';
        header('Location: change_password.php');
        exit();
    }

    // Check if new passwords match
    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = 'New passwords do not match.';
        header('Location: change_password.php');
        exit();
    }

    // Validate new password strength
    if (strlen($new_password) < 8) {
        $_SESSION['error'] = 'Password must be at least 8 characters long.';
        header('Location: change_password.php');
        exit();
    }

    if (!preg_match('/[A-Z]/', $new_password)) {
        $_SESSION['error'] = 'Password must contain at least one uppercase letter.';
        header('Location: change_password.php');
        exit();
    }

    if (!preg_match('/[a-z]/', $new_password)) {
        $_SESSION['error'] = 'Password must contain at least one lowercase letter.';
        header('Location: change_password.php');
        exit();
    }

    if (!preg_match('/[0-9]/', $new_password)) {
        $_SESSION['error'] = 'Password must contain at least one number.';
        header('Location: change_password.php');
        exit();
    }

    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $new_password)) {
        $_SESSION['error'] = 'Password must contain at least one special character (!@#$%^&*).';
        header('Location: change_password.php');
        exit();
    }

    // Get current password hash from database
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        $_SESSION['error'] = 'User not found.';
        header('Location: change_password.php');
        exit();
    }

    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        $_SESSION['error'] = 'Current password is incorrect.';
        header('Location: change_password.php');
        exit();
    }

    // Update password
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed_password, $user_id);

    if ($stmt->execute()) {
        // Log the change in audit logs
        $action = 'password_change';
        $description = 'User changed their password';
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $stmt_audit = $conn->prepare("INSERT INTO audit_logs (user_id, action, description, ip_address, timestamp) VALUES (?, ?, ?, ?, NOW())");
        $stmt_audit->bind_param("isss", $user_id, $action, $description, $ip_address);
        $stmt_audit->execute();

        $_SESSION['success'] = 'Password updated successfully! Please log in again for security purposes.';
        header('Location: login.php');
        exit();
    } else {
        $_SESSION['error'] = 'Failed to update password. Please try again.';
        header('Location: change_password.php');
        exit();
    }
}
?>
