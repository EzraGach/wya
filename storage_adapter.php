<?php
/**
 * Storage Adapter - Hybrid approach using SQL metadata + Tebi/Local file storage
 */

require_once 'database.php';
require_once 'tebi_config.php';

// Local file storage directory
define('LOCAL_STORAGE_PATH', __DIR__ . '/storage/files/');
define('ENABLE_TEBI_STORAGE', getenv('ENABLE_TEBI_STORAGE') === 'true');

// Ensure storage directory exists
if (!is_dir(LOCAL_STORAGE_PATH)) {
    mkdir(LOCAL_STORAGE_PATH, 0755, true);
}

/**
 * Get all buckets with metadata
 */
function listBuckets() {
    return getAllBuckets();
}

/**
 * Upload file and track in database
 * Returns array with success status and object ID
 */
function uploadFile($bucket_name, $key, $tmp_path, $user_id = null) {
    global $conn;
    
    // Validate inputs
    if (empty($bucket_name) || empty($key) || !file_exists($tmp_path)) {
        return ['success' => false, 'error' => 'Invalid input parameters'];
    }
    
    // Get bucket ID
    $bucket_id = getBucketId($bucket_name);
    if (!$bucket_id) {
        return ['success' => false, 'error' => 'Bucket not found'];
    }
    
    // Get file info
    $file_size = filesize($tmp_path);
    $mime_type = mime_content_type($tmp_path) ?: 'application/octet-stream';
    
    // Determine storage type
    $storage_type = ENABLE_TEBI_STORAGE ? 'tebi' : 'local';
    $file_path = null;
    
    try {
        if ($storage_type === 'local') {
            // Store locally
            $safe_key = basename($key);
            $destination = LOCAL_STORAGE_PATH . $bucket_name . '/' . $safe_key;
            
            // Ensure bucket directory exists
            $bucket_dir = LOCAL_STORAGE_PATH . $bucket_name;
            if (!is_dir($bucket_dir)) {
                mkdir($bucket_dir, 0755, true);
            }
            
            if (!move_uploaded_file($tmp_path, $destination)) {
                return ['success' => false, 'error' => 'Failed to move uploaded file'];
            }
            $file_path = $destination;
        } else if ($storage_type === 'tebi') {
            // Store in Tebi
            $result = uploadToTebi($bucket_name, $key, $tmp_path);
            if (!$result['success']) {
                return $result;
            }
        }
        
        // Insert database record
        $object_id = insertObject($bucket_id, $key, $file_path, $file_size, $mime_type, $storage_type, $user_id);
        
        if ($object_id) {
            // Log audit
            logAudit($object_id, 'UPLOAD', $user_id, $_SERVER['REMOTE_ADDR'] ?? '', "File uploaded: $key");
            return ['success' => true, 'object_id' => $object_id, 'storage' => $storage_type];
        } else {
            return ['success' => false, 'error' => 'Failed to insert database record'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * List objects in a bucket
 */
function listObjects($bucket_name) {
    $bucket_id = getBucketId($bucket_name);
    if (!$bucket_id) {
        return [];
    }
    return getObjectsByBucket($bucket_id);
}

/**
 * Upload to Tebi S3 storage
 */
function uploadToTebi($bucket_name, $key, $path) {
    global $s3Client;
    
    try {
        $result = $s3Client->putObject([
            'Bucket'     => $bucket_name,
            'Key'        => $key,
            'SourceFile' => $path
        ]);
        
        if ($result['@metadata']['statusCode'] == 200) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Tebi upload failed: ' . $result['@metadata']['statusCode']];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Tebi error: ' . $e->getMessage()];
    }
}

/**
 * Download file from storage
 */
function downloadFile($object_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT file_key, file_path, storage_type, bucket_id FROM objects WHERE id = ?");
    $stmt->bind_param("i", $object_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $object = $result->fetch_assoc();
    $stmt->close();
    
    if (!$object) {
        return ['success' => false, 'error' => 'Object not found'];
    }
    
    if ($object['storage_type'] === 'local') {
        return ['success' => true, 'file_path' => $object['file_path'], 'key' => $object['file_key']];
    } else {
        // For Tebi, generate a download URL or stream from Tebi
        return ['success' => true, 'file_path' => null, 'storage' => 'tebi', 'key' => $object['file_key']];
    }
}

/**
 * Delete file from storage
 */
function deleteFile($object_id, $user_id = null) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT file_path, storage_type, file_key FROM objects WHERE id = ?");
    $stmt->bind_param("i", $object_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $object = $result->fetch_assoc();
    $stmt->close();
    
    if (!$object) {
        return ['success' => false, 'error' => 'Object not found'];
    }
    
    try {
        // 1. Log the delete action BEFORE deleting the object
        logAudit($object_id, 'DELETE', $user_id, $_SERVER['REMOTE_ADDR'] ?? '', "File deletion initiated for: " . $object['file_key']);
        
        // 2. Delete the physical file from storage
        if ($object['storage_type'] === 'local' && file_exists($object['file_path'])) {
            unlink($object['file_path']);
        }
        
        // 3. Delete from the database. ON DELETE CASCADE will handle audit_logs.
        $delete_stmt = $conn->prepare("DELETE FROM objects WHERE id = ?");
        $delete_stmt->bind_param("i", $object_id);
        $delete_stmt->execute();
        $delete_stmt->close();
        
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function deleteObject(string $bucket, string $key, int $user_id = null): array {
    // Get the object ID based on bucket and key
    $object_id = getObjectId($bucket, $key);

    if (!$object_id) {
        return ['success' => false, 'error' => 'Object not found'];
    }

    // Call the existing deleteFile function with the object ID
    return deleteFile($object_id, $user_id);
}



/**
 * Get file metadata
 */
function getFileMetadata($object_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM objects WHERE id = ?");
    $stmt->bind_param("i", $object_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $metadata = $result->fetch_assoc();
    $stmt->close();
    
    return $metadata;
}

/**
 * Helper function to get object ID by bucket and key
 */
function getObjectId(string $bucket, string $key): ?int {
    global $conn;
    $bucket_id = getBucketId($bucket);
    $stmt = $conn->prepare("SELECT id FROM objects WHERE bucket_id = ? AND file_key = ?");
    $stmt->bind_param("is", $bucket_id, $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $object = $result->fetch_assoc();
    $stmt->close();

    return $object ? (int)$object['id'] : null;
}
?>
