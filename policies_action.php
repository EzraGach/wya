<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'database.php';

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';

    if (empty($name)) {
        $_SESSION['error'] = 'Bucket name is required';
        header('Location: policies.php');
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO buckets (name, description, created_at) VALUES (?, ?, NOW())");
    if (!$stmt) {
        $_SESSION['error'] = 'Database error: ' . $conn->error;
        header('Location: policies.php');
        exit();
    }

    $stmt->bind_param("ss", $name, $description);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Bucket created successfully';
    } else {
        $_SESSION['error'] = 'Error creating bucket: ' . $stmt->error;
    }
    $stmt->close();

} elseif ($action === 'update') {
    $policy_id = $_POST['policy_id'] ?? '';
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';

    if (empty($policy_id) || empty($name)) {
        $_SESSION['error'] = 'Missing required fields';
        header('Location: policies.php');
        exit();
    }

    $stmt = $conn->prepare("UPDATE buckets SET name = ?, description = ? WHERE id = ?");
    if (!$stmt) {
        $_SESSION['error'] = 'Database error: ' . $conn->error;
        header('Location: policies.php');
        exit();
    }

    $stmt->bind_param("ssi", $name, $description, $policy_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Bucket updated successfully';
    } else {
        $_SESSION['error'] = 'Error updating bucket: ' . $stmt->error;
    }
    $stmt->close();

} elseif ($action === 'delete') {
    $policy_id = $_POST['policy_id'] ?? '';

    if (empty($policy_id)) {
        $_SESSION['error'] = 'Policy ID is required';
        header('Location: policies.php');
        exit();
    }

    // Check if bucket has files
    $check = $conn->query("SELECT COUNT(*) as count FROM objects WHERE bucket_id = $policy_id");
    $result = $check->fetch_assoc();
    
    if ($result['count'] > 0) {
        $_SESSION['error'] = 'Cannot delete bucket with ' . $result['count'] . ' files. Please remove files first.';
        header('Location: policies.php');
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM buckets WHERE id = ?");
    if (!$stmt) {
        $_SESSION['error'] = 'Database error: ' . $conn->error;
        header('Location: policies.php');
        exit();
    }

    $stmt->bind_param("i", $policy_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Bucket deleted successfully';
    } else {
        $_SESSION['error'] = 'Error deleting bucket: ' . $stmt->error;
    }
    $stmt->close();
}

header('Location: policies.php');
exit();
