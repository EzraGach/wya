<?php
// Database configuration - Use environment variables for security
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: 'root';
$db_name = getenv('DB_NAME') ?: 'dlp_db';

// Create a new database connection using mysqli
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    die("Database connection failed: " . htmlspecialchars($conn->connect_error));
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

/**
 * Get bucket ID by name
 */
function getBucketId($bucket_name) {
    global $conn;
    $stmt = $conn->prepare("SELECT id FROM buckets WHERE name = ? LIMIT 1");
    $stmt->bind_param("s", $bucket_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row['id'] : null;
}

/**
 * Get all buckets
 */
function getAllBuckets() {
    global $conn;
    $result = $conn->query("SELECT id, name FROM buckets WHERE is_active = TRUE");
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get all objects in a bucket
 */
function getObjectsByBucket($bucket_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT id, file_key as 'key', file_size, mime_type, uploaded_at, is_scanned FROM objects WHERE bucket_id = ? ORDER BY uploaded_at DESC");
    $stmt->bind_param("i", $bucket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $objects = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $objects;
}

/**
 * Insert new object record
 */
function insertObject($bucket_id, $key, $file_path, $file_size, $mime_type, $storage_type = 'local', $uploaded_by = null) {
    global $conn;
    $hash = hash_file('sha256', $file_path);
    $stmt = $conn->prepare("INSERT INTO objects (bucket_id, file_key, file_path, file_size, mime_type, storage_type, uploaded_by, hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississis", $bucket_id, $key, $file_path, $file_size, $mime_type, $storage_type, $uploaded_by, $hash);
    $success = $stmt->execute();
    $object_id = $stmt->insert_id;
    $stmt->close();
    return $success ? $object_id : false;
}

/**
 * Log audit entry
 */
function logAudit($object_id, $action, $user_id, $ip_address, $details = '') {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO audit_logs (object_id, action, user_id, ip_address, details) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isiss", $object_id, $action, $user_id, $ip_address, $details);
    $stmt->execute();
    $stmt->close();
}

/**
 * Update scan status
 */
function updateScanStatus($object_id, $is_scanned, $scan_results = '') {
    global $conn;
    $stmt = $conn->prepare("UPDATE objects SET is_scanned = ?, scan_results = ? WHERE id = ?");
    $stmt->bind_param("isi", $is_scanned, $scan_results, $object_id);
    $stmt->execute();
    $stmt->close();
}
?>
