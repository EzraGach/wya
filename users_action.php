<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'database.php';

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';

    if (empty($username) || empty($email) || empty($password)) {
        $_SESSION['error'] = 'All fields are required';
        header('Location: users.php');
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Invalid email format';
        header('Location: users.php');
        exit();
    }

    // Check if username or email already exists
    $check = $conn->query("SELECT id FROM users WHERE username = '$username' OR email = '$email' LIMIT 1");
    if ($check->num_rows > 0) {
        $_SESSION['error'] = 'Username or email already exists';
        header('Location: users.php');
        exit();
    }

    // Hash password
    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    // Validate role
    if (!in_array($role, ['user', 'admin'])) {
        $role = 'user';
    }

    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
    if (!$stmt) {
        $_SESSION['error'] = 'Database error: ' . $conn->error;
        header('Location: users.php');
        exit();
    }

    $stmt->bind_param("ssss", $username, $email, $password_hash, $role);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'User created successfully';
    } else {
        $_SESSION['error'] = 'Error creating user: ' . $stmt->error;
    }
    $stmt->close();

} elseif ($action === 'update') {
    $user_id = $_POST['user_id'] ?? '';
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';

    if (empty($user_id) || empty($username) || empty($email)) {
        $_SESSION['error'] = 'Missing required fields';
        header('Location: users.php');
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Invalid email format';
        header('Location: users.php');
        exit();
    }

    // Check if email or username is used by another user
    $check = $conn->query("SELECT id FROM users WHERE (username = '$username' OR email = '$email') AND id != $user_id LIMIT 1");
    if ($check->num_rows > 0) {
        $_SESSION['error'] = 'Username or email already in use';
        header('Location: users.php');
        exit();
    }

    // Validate role
    if (!in_array($role, ['user', 'admin'])) {
        $role = 'user';
    }

    if (!empty($password)) {
        // Update with new password
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, password = ?, role = ? WHERE id = ?");
        if (!$stmt) {
            $_SESSION['error'] = 'Database error: ' . $conn->error;
            header('Location: users.php');
            exit();
        }
        $stmt->bind_param("ssssi", $username, $email, $password_hash, $role, $user_id);
    } else {
        // Update without changing password
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
        if (!$stmt) {
            $_SESSION['error'] = 'Database error: ' . $conn->error;
            header('Location: users.php');
            exit();
        }
        $stmt->bind_param("sssi", $username, $email, $role, $user_id);
    }

    if ($stmt->execute()) {
        $_SESSION['success'] = 'User updated successfully';
    } else {
        $_SESSION['error'] = 'Error updating user: ' . $stmt->error;
    }
    $stmt->close();

} elseif ($action === 'delete') {
    $user_id = $_POST['user_id'] ?? '';

    if (empty($user_id)) {
        $_SESSION['error'] = 'User ID is required';
        header('Location: users.php');
        exit();
    }

    // Prevent deleting the current logged-in user
    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['error'] = 'Cannot delete your own account';
        header('Location: users.php');
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    if (!$stmt) {
        $_SESSION['error'] = 'Database error: ' . $conn->error;
        header('Location: users.php');
        exit();
    }

    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'User deleted successfully';
    } else {
        $_SESSION['error'] = 'Error deleting user: ' . $stmt->error;
    }
    $stmt->close();
}

header('Location: users.php');
exit();
