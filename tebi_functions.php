<?php
/**
 * Legacy Tebi Functions - Now redirected to Storage Adapter
 * Maintained for backward compatibility
 */

require_once 'storage_adapter.php';

// These functions are now deprecated in favor of storage_adapter.php
// but kept for backward compatibility with existing code

function listBuckets() {
    return \listBuckets();
}

function uploadFile($bucket, $key, $path, $user_id = null) {
    return \uploadFile($bucket, $key, $path, $user_id);
}

function listObjects($bucket) {
    return \listObjects($bucket);
}
?>