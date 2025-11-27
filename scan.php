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

// Handle file deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_object']) && isset($_POST['object_key'])) {
    $bucket = $_POST['bucket'] ?? '';
    $object_key = $_POST['object_key'];

    if (!empty($bucket) && !empty($object_key)) {
        $delete_result = deleteObject($bucket, $object_key, $_SESSION['user_id']);

        header('Content-Type: application/json');
        if ($delete_result['success']) {
            echo json_encode(['success' => true, 'message' => 'File deleted successfully.']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $delete_result['error'] ?? 'Unknown error on server']);
        }
        exit();
    }
}

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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DLP - File Scanning</title>

<style>
    * {
        box-sizing: border-box;
    }

    body { 
        margin: 0; 
        font-family: 'Poppins', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
        display: flex; 
        color: #343a40;
        min-height: 100vh;
    }

    .sidebar { 
        width: 260px; 
        background: linear-gradient(180deg, #7a0010 0%, #5b000b 100%);
        color: white; 
        height: 100vh; 
        position: fixed; 
        padding-top: 20px; 
        overflow-y: auto;
        box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
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
        padding: 14px 20px; 
        cursor: pointer;
        border-left: 4px solid transparent;
        transition: all 0.3s ease;
    }
    
    .sidebar ul li:hover { 
        background-color: rgba(255, 255, 255, 0.1);
        padding-left: 24px;
    }
    
    .sidebar ul li.active {
        background: rgba(255, 255, 255, 0.15);
        border-left: 4px solid #fff;
        padding-left: 16px;
    }

    .sidebar a {
        color: white;
        text-decoration: none;
    }

    .main { 
        margin-left: 260px; 
        padding: 40px; 
        width: calc(100% - 260px);
        max-width: 1400px;
    }

    .page-header {
        margin-bottom: 35px;
    }

    .page-header h2 {
        font-size: 32px;
        font-weight: 700;
        margin: 0 0 8px 0;
        color: #212529;
    }

    .page-header p {
        margin: 0;
        color: #6c757d;
        font-size: 15px;
    }

    .grid-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
        margin-bottom: 25px;
    }

    .card {
        background: white; 
        padding: 28px;
        border-radius: 16px; 
        box-shadow: 0 2px 20px rgba(0, 0, 0, 0.06);
        border: 1px solid rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
    }

    .card:hover {
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .card-full {
        grid-column: 1 / -1;
    }

    .card h3 {
        margin: 0 0 20px 0;
        font-size: 20px;
        font-weight: 600;
        color: #212529;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .card h3::before {
        content: '';
        width: 4px;
        height: 24px;
        background: linear-gradient(180deg, #7a0010 0%, #5b000b 100%);
        border-radius: 2px;
    }

    .message {
        padding: 16px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: none;
        opacity: 0;
        transition: opacity 0.5s ease-in-out;
        font-size: 14px;
        font-weight: 500;
    }

    .success {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .error-box {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    /* Upload Section Redesign */
    .upload-section {
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        border: 2px dashed #dee2e6;
        border-radius: 12px;
        padding: 30px;
        text-align: center;
        transition: all 0.3s ease;
    }

    .upload-section:hover {
        border-color: #7a0010;
        background: linear-gradient(135deg, #ffffff 0%, #fff5f7 100%);
    }

    .upload-icon {
        width: 64px;
        height: 64px;
        margin: 0 auto 20px;
        background: linear-gradient(135deg, #7a0010 0%, #5b000b 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 32px;
    }

    .file-input-wrapper {
        position: relative;
        overflow: hidden;
        display: inline-block;
        margin: 20px 0;
    }

    .file-input-wrapper input[type=file] {
        position: absolute;
        left: -9999px;
    }

    .file-label {
        display: inline-block;
        padding: 14px 32px;
        background: white;
        color: #495057;
        border: 2px solid #dee2e6;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
        font-size: 14px;
    }

    .file-label:hover {
        background: #f8f9fa;
        border-color: #7a0010;
        color: #7a0010;
    }

    .file-name {
        display: block;
        margin-top: 12px;
        color: #6c757d;
        font-size: 14px;
        min-height: 20px;
    }

    .form-row {
        display: flex;
        gap: 15px;
        align-items: center;
        justify-content: center;
        margin-top: 20px;
        flex-wrap: wrap;
    }

    select {
        padding: 12px 20px;
        border-radius: 8px;
        border: 2px solid #dee2e6;
        font-size: 14px;
        font-family: inherit;
        color: #495057;
        background: white;
        cursor: pointer;
        transition: all 0.3s ease;
        min-width: 200px;
    }

    select:hover, select:focus {
        border-color: #7a0010;
        outline: none;
    }

    button {
        padding: 12px 32px;
        background: linear-gradient(135deg, #7a0010 0%, #5b000b 100%);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 600;
        font-size: 14px;
        box-shadow: 0 4px 12px rgba(122, 0, 16, 0.3);
    }

    button:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(122, 0, 16, 0.4);
    }

    button:active {
        transform: translateY(0);
    }

    #progress-container {
        display: none;
        margin-top: 20px;
        background-color: #e9ecef;
        border-radius: 10px;
        padding: 4px;
        overflow: hidden;
    }

    #progress-bar {
        width: 0%;
        height: 32px;
        background: linear-gradient(90deg, #7a0010 0%, #a50014 100%);
        border-radius: 8px;
        text-align: center;
        color: white;
        line-height: 32px;
        font-weight: 600;
        transition: width 0.3s ease;
        box-shadow: 0 2px 8px rgba(122, 0, 16, 0.3);
    }

    /* Lists */
    .bucket-list ul, .object-list ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .bucket-list li {
        padding: 16px;
        border-radius: 8px;
        margin-bottom: 8px;
        background: #f8f9fa;
        transition: all 0.3s ease;
        border-left: 4px solid transparent;
    }

    .bucket-list li:hover {
        background: #e9ecef;
        border-left-color: #7a0010;
        transform: translateX(4px);
    }

    .bucket-list a {
        color: #212529;
        text-decoration: none;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .object-list li {
        padding: 18px;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.3s ease;
    }

    .object-list li:hover {
        background-color: #f8f9fa;
        padding-left: 24px;
    }

    .object-list li:last-child {
        border-bottom: none;
    }

    .object-details {
        flex-grow: 1;
    }

    .object-name {
        font-weight: 600;
        color: #212529;
        margin-bottom: 6px;
        display: block;
    }

    .object-info {
        font-size: 13px;
        color: #6c757d;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }

    .info-item {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .info-label {
        color: #868e96;
    }

    .info-value {
        color: #495057;
        font-weight: 500;
    }

    .delete-btn {
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        border: none;
        cursor: pointer;
        padding: 10px;
        border-radius: 8px;
        transition: all 0.3s ease;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 8px rgba(220, 53, 69, 0.2);
    }

    .delete-btn:hover {
        transform: scale(1.1);
        box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
    }

    .delete-btn svg {
        width: 18px;
        height: 18px;
        fill: white;
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #868e96;
    }

    .empty-state svg {
        width: 64px;
        height: 64px;
        margin-bottom: 16px;
        opacity: 0.3;
    }

    @media (max-width: 1024px) {
        .grid-container {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .sidebar {
            width: 100%;
            height: auto;
            position: relative;
        }

        .main {
            margin-left: 0;
            width: 100%;
            padding: 20px;
        }

        .form-row {
            flex-direction: column;
        }

        select {
            width: 100%;
        }
    }
</style>

</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">
    <div class="page-header">
        <h2>File Scanning</h2>
        <p>Upload and manage your files with automated security scanning</p>
    </div>

    <!-- Message containers -->
    <div id="successMessage" class="message success"></div>
    <div id="errorMessage" class="message error-box"></div>

    <div class="grid-container">
        <!-- Upload Card -->
        <div class="card card-full">
            <h3>Upload New File</h3>
            <form id="uploadForm" action="scan.php" method="post" enctype="multipart/form-data">
                <div class="upload-section">
                    <div class="upload-icon">📁</div>
                    <div class="file-input-wrapper">
                        <label for="fileToUpload" class="file-label">
                            Choose File
                        </label>
                        <input type="file" name="fileToUpload" id="fileToUpload" required>
                    </div>
                    <div class="file-name" id="file-name-display">No file selected</div>
                    
                    <div class="form-row">
                        <select name="bucket" required>
                            <option value="">-- Select Bucket --</option>
                            <?php foreach ($buckets as $bucket): ?>
                                <option value="<?php echo htmlspecialchars($bucket['name']); ?>">
                                    <?php echo htmlspecialchars($bucket['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit">Upload and Scan</button>
                    </div>
                </div>
                
                <div id="progress-container">
                    <div id="progress-bar">
                        <span id="progress-text">0%</span>
                    </div>
                </div>
            </form>
        </div>

        <!-- Buckets Card -->
        <div class="card bucket-list">
            <h3>Available Buckets</h3>
            <?php if (!empty($buckets)): ?>
                <ul>
                    <?php foreach ($buckets as $bucket): ?>
                        <li>
                            <a href="?bucket=<?php echo urlencode($bucket['name']); ?>">
                                📦 <?php echo htmlspecialchars($bucket['name']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="empty-state">
                    <p>No buckets available. Create one in settings.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Objects Card -->
        <div class="card object-list">
            <h3>Files in <?php echo htmlspecialchars($selected_bucket ?: 'Selected Bucket'); ?></h3>
            <ul id="object-list-ul">
                <?php if (!empty($selected_bucket)): ?>
                    <?php if (!empty($objects)): ?>
                        <?php foreach ($objects as $object): ?>
                            <li data-key="<?php echo htmlspecialchars($object['key']); ?>">
                                <div class="object-details">
                                    <span class="object-name"><?php echo htmlspecialchars($object['key']); ?></span>
                                    <div class="object-info">
                                        <div class="info-item">
                                            <span class="info-label">Size:</span>
                                            <span class="info-value"><?php echo number_format($object['file_size'] / 1024, 2); ?> KB</span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Type:</span>
                                            <span class="info-value"><?php echo htmlspecialchars($object['mime_type']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Uploaded:</span>
                                            <span class="info-value"><?php echo date('M d, Y', strtotime($object['uploaded_at'])); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Status:</span>
                                            <span class="info-value"><?php echo $object['is_scanned'] ? '✓ Scanned' : '⏳ Pending'; ?></span>
                                        </div>
                                    </div>
                                </div>
                                <button class="delete-btn" title="Delete file" onclick="deleteObject(this, '<?php echo htmlspecialchars($selected_bucket); ?>', '<?php echo htmlspecialchars($object['key']); ?>')">
                                    <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"></path></svg>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19.35 10.04C18.67 6.59 15.64 4 12 4c-1.48 0-2.85.43-4.01 1.17l1.46 1.46C10.21 6.23 11.08 6 12 6c3.04 0 5.5 2.46 5.5 5.5v.5H19c1.66 0 3 1.34 3 3 0 1.13-.64 2.11-1.56 2.62l1.45 1.45C23.16 18.16 24 16.68 24 15c0-2.64-2.05-4.78-4.65-4.96zM3 5.27l2.75 2.74C2.56 8.15 0 10.77 0 14c0 3.31 2.69 6 6 6h11.73l2 2L21 20.73 4.27 4 3 5.27zM7.73 10l8 8H6c-2.21 0-4-1.79-4-4s1.79-4 4-4h1.73z"/>
                            </svg>
                            <p>No files in this bucket</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/>
                        </svg>
                        <p>Select a bucket to view files</p>
                    </div>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('uploadForm');
    const progressBarContainer = document.getElementById('progress-container');
    const progressBar = document.getElementById('progress-bar');
    const progressText = document.getElementById('progress-text');
    const fileInput = document.getElementById('fileToUpload');
    const fileNameDisplay = document.getElementById('file-name-display');

    // Display initial messages
    <?php if (!empty($upload_message)): ?>
        showFlashMessage('<?php echo addslashes($upload_message); ?>', 'success');
    <?php endif; ?>
    <?php if (!empty($upload_error)): ?>
        showFlashMessage('<?php echo addslashes($upload_error); ?>', 'error');
    <?php endif; ?>

    // Update file name display
    fileInput.addEventListener('change', function() {
        fileNameDisplay.textContent = this.files.length > 0 ? this.files[0].name : 'No file selected';
    });

    // Handle form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        if (fileInput.files.length === 0) {
            alert('Please select a file to upload.');
            return;
        }

        const formData = new FormData(form);
        const xhr = new XMLHttpRequest();

        xhr.open('POST', form.action, true);

        progressBar.style.width = '0%';
        progressText.textContent = '0%';
        progressBarContainer.style.display = 'block';

        xhr.upload.addEventListener('progress', function(event) {
            if (event.lengthComputable) {
                const percentComplete = Math.round((event.loaded / event.total) * 100);
                const displayPercent = Math.min(percentComplete, 99);
                progressBar.style.width = displayPercent + '%';
                progressText.textContent = displayPercent + '%';
            }
        });

        xhr.addEventListener('load', function() {
            progressBar.style.width = '100%';
            progressText.textContent = 'Processing...';

            if (xhr.status >= 200 && xhr.status < 300) {
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            } else {
                showFlashMessage('An error occurred during the upload. Please try again.', 'error');
                progressBarContainer.style.display = 'none';
            }
        });

        xhr.send(formData);
    });
});

function showFlashMessage(message, type) {
    const successBox = document.getElementById('successMessage');
    const errorBox = document.getElementById('errorMessage');
    const box = type === 'success' ? successBox : errorBox;

    box.textContent = message;
    box.style.display = 'block';
    setTimeout(() => { box.style.opacity = 1; }, 10);

    setTimeout(() => {
        box.style.opacity = 0;
        setTimeout(() => { box.style.display = 'none'; }, 500);
    }, 5000);
}

function deleteObject(element, bucket, key) {
    if (!confirm(`Are you sure you want to delete ${key}?`)) {
        return;
    }

    const formData = new FormData();
    formData.append('delete_object', '1');
    formData.append('bucket', bucket);
    formData.append('object_key', key);

    fetch('scan.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        return response.json().then(data => ({ ok: response.ok, data }));
    })
    .then(({ ok, data }) => {
        if (ok && data.success) {
            const listItem = element.closest('li');
            listItem.style.transition = 'opacity 0.5s, transform 0.5s';
            listItem.style.opacity = 0;
            listItem.style.transform = 'translateX(-20px)';
            setTimeout(() => listItem.remove(), 500);
            showFlashMessage(`Successfully deleted ${key}.`, 'success');
        } else {
            showFlashMessage(`Deletion failed: ${data.message}`, 'error');
        }
    }).catch(error => showFlashMessage('A network error occurred during deletion.', 'error'));
}
</script>
</body>
</html>