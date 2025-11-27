<?php
session_start();
require_once 'storage_adapter.php';

// Check authentication
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);

$upload_message = '';
$upload_error = '';

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fileToUpload'])) {
    $bucket = $_POST['bucket'] ?? '';
    
    if (empty($bucket)) {
        $upload_error = 'Please select a bucket.';
    } elseif ($_FILES['fileToUpload']['error'] != UPLOAD_ERR_OK) {
        $upload_error = 'File upload error: ' . $_FILES['fileToUpload']['error'];
    } else {
        $key = basename($_FILES["fileToUpload"]["name"]);
        $path = $_FILES['fileToUpload']['tmp_name'];
        
        $result = uploadFile($bucket, $key, $path, $_SESSION['user_id']);
        
        if ($result['success']) {
            $upload_message = 'File uploaded successfully! Object ID: ' . $result['object_id'];
        } else {
            $upload_error = 'Upload failed: ' . ($result['error'] ?? 'Unknown error');
        }
    }
}

$buckets = listBuckets();
$selected_bucket = $_GET['bucket'] ?? '';
$objects = $selected_bucket ? listObjects($selected_bucket) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DLP - File Scanning</title>

<style>
    body { 
        margin:0; 
        font-family:Poppins,sans-serif; 
        background:#f5f6fa; 
        display:flex; 
    }

    .sidebar { 
        width:280px; 
        background:#7a0010; 
        color:white; 
        height:100vh; 
        position:fixed; 
        padding-top:20px; 
        overflow-y: auto;
    }
    
    .sidebar h2 {
        text-align: center;
        margin: 0 0 20px 0;
        font-size: 16px;
    }
    
    .sidebar ul { 
        list-style: none; 
        margin: 0; 
        padding: 0; 
    }
    
    .sidebar ul li { 
        padding:14px 20px; 
        cursor:pointer; 
        border-bottom:1px solid rgba(255,255,255,0.1); 
    }
    .sidebar ul li:hover { 
        background:#5b000b; 
    }
    
    .sidebar ul li.active {
        background: #5b000b;
        border-left: 4px solid #fff;
        padding-left: 16px;
    }

    .main { 
        margin-left:280px; 
        padding:20px; 
        width:calc(100% - 280px); 
    }

    .upload-box {
        background:white; 
        padding:20px;
        border-radius:10px; 
        box-shadow:0 2px 6px rgba(0,0,0,0.1);
        text-align:center;
        margin-bottom: 20px;
    }

    .message {
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 15px;
    }

    .success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .error-box {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    input[type=file] {
        margin:20px 0;
    }

    select, input {
        padding: 8px;
        margin: 5px;
        border-radius: 4px;
        border: 1px solid #ddd;
    }

    button {
        padding:10px 20px; 
        background:#7a0010; 
        color:white; 
        border:none; 
        border-radius:6px;
        cursor: pointer;
    }

    button:hover {
        background: #5b000b;
    }

    .bucket-list, .object-list {
        background:white; 
        padding:20px;
        border-radius:10px; 
        box-shadow:0 2px 6px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }

    .bucket-list ul, .object-list ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .bucket-list li, .object-list li {
        padding: 10px;
        border-bottom: 1px solid #eee;
    }

    .bucket-list li:last-child, .object-list li:last-child {
        border-bottom: none;
    }

    .bucket-list a {
        color: #7a0010;
        text-decoration: none;
    }

    .bucket-list a:hover {
        text-decoration: underline;
    }

    .object-info {
        font-size: 12px;
        color: #666;
        margin-top: 5px;
    }
</style>

</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">
    <h2>File Scanning</h2>

    <?php if (!empty($upload_message)): ?>
        <div class="message success"><?php echo htmlspecialchars($upload_message); ?></div>
    <?php endif; ?>

    <?php if (!empty($upload_error)): ?>
        <div class="message error-box"><?php echo htmlspecialchars($upload_error); ?></div>
    <?php endif; ?>

    <div class="upload-box">
        <h3>Upload a file to scan</h3>
        <form action="scan.php" method="post" enctype="multipart/form-data">
            <select name="bucket" required>
                <option value="">-- Select Bucket --</option>
                <?php foreach ($buckets as $bucket): ?>
                    <option value="<?php echo htmlspecialchars($bucket['name']); ?>">
                        <?php echo htmlspecialchars($bucket['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="file" name="fileToUpload" id="fileToUpload" required>
            <br>
            <button type="submit">Upload and Scan</button>
        </form>
    </div>

    <div class="bucket-list">
        <h3>Buckets</h3>
        <ul>
            <?php if (!empty($buckets)): ?>
                <?php foreach ($buckets as $bucket): ?>
                    <li>
                        <a href="?bucket=<?php echo urlencode($bucket['name']); ?>">
                            📁 <?php echo htmlspecialchars($bucket['name']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li style="color: #999;">No buckets available. Create one in settings.</li>
            <?php endif; ?>
        </ul>
    </div>

    <?php if (!empty($selected_bucket)): ?>
    <div class="object-list">
        <h3>Objects in <?php echo htmlspecialchars($selected_bucket); ?></h3>
        <?php if (!empty($objects)): ?>
            <ul>
                <?php foreach ($objects as $object): ?>
                    <li>
                        📄 <?php echo htmlspecialchars($object['key']); ?>
                        <div class="object-info">
                            Size: <?php echo number_format($object['file_size'] / 1024, 2); ?> KB | 
                            Type: <?php echo htmlspecialchars($object['mime_type']); ?> |
                            Uploaded: <?php echo date('Y-m-d H:i', strtotime($object['uploaded_at'])); ?> |
                            Scanned: <?php echo $object['is_scanned'] ? 'Yes ✓' : 'No'; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No files in this bucket.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
