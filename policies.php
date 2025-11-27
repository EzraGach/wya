<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'database.php';

// Get bucket policies and statistics
$policies = [];
$result = $conn->query("SELECT id, name, created_at FROM buckets ORDER BY created_at DESC");
if ($result) {
    $policies = $result->fetch_all(MYSQLI_ASSOC);
}

// Get policy statistics
$total_buckets = count($policies);
$total_objects = $conn->query("SELECT COUNT(*) as count FROM objects")->fetch_assoc()['count'];
$scanned_count = $conn->query("SELECT COUNT(*) as count FROM objects WHERE is_scanned = TRUE")->fetch_assoc()['count'];
$unscanned_count = $total_objects - $scanned_count;
$scan_coverage = $total_objects > 0 ? round(($scanned_count / $total_objects) * 100, 1) : 0;

// Get file type policies
$mime_types = [];
$result = $conn->query("SELECT DISTINCT mime_type, COUNT(*) as count FROM objects GROUP BY mime_type ORDER BY count DESC LIMIT 15");
if ($result) {
    $mime_types = $result->fetch_all(MYSQLI_ASSOC);
}

// Get retention data - oldest and newest files
$retention = $conn->query("SELECT 
    MIN(uploaded_at) as oldest_file, 
    MAX(uploaded_at) as newest_file,
    COUNT(*) as total_files,
    SUM(file_size) as total_size
    FROM objects")->fetch_assoc();

// Get access control stats
$access_logs_count = $conn->query("SELECT COUNT(*) as count FROM audit_logs")->fetch_assoc()['count'];
$unique_users = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM audit_logs")->fetch_assoc()['count'];
$unique_ips = $conn->query("SELECT COUNT(DISTINCT ip_address) as count FROM audit_logs")->fetch_assoc()['count'];

// Get file size statistics
$size_stats = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(file_size) as total_size,
    AVG(file_size) as avg_size,
    MAX(file_size) as max_size,
    MIN(file_size) as min_size
    FROM objects")->fetch_assoc();

function formatBytes($bytes) {
    if ($bytes === null) return '0 B';
    if ($bytes >= 1099511627776) return round($bytes / 1099511627776, 2) . ' TB';
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return round($bytes, 2) . ' B';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DLP - Data Policies</title>

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

    .stat-card.scan {
        border-left-color: #4c6ef5;
    }

    .stat-card.retention {
        border-left-color: #15aabf;
    }

    .stat-card.access {
        border-left-color: #f08c00;
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

    .policy-box {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0px 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 20px;
    }

    .policy-item {
        padding: 15px;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .policy-item:last-child {
        border-bottom: none;
    }

    .policy-info h4 {
        margin: 0 0 5px 0;
        color: #1a1a1a;
        font-size: 14px;
    }

    .policy-info p {
        margin: 0;
        color: #666;
        font-size: 12px;
    }

    .policy-status {
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .policy-status.active {
        background: #d0ebff;
        color: #1971c2;
    }

    .policy-status.review {
        background: #fff3bf;
        color: #92400e;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0px 2px 8px rgba(0,0,0,0.08);
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

    .type-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        background: #e7f5ff;
        color: #1971c2;
    }

    .policy-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 10px;
        margin-top: 10px;
    }

    .policy-info-item {
        background: #f9f9f9;
        padding: 10px;
        border-radius: 6px;
        font-size: 12px;
    }

    .policy-info-item strong {
        color: #1a1a1a;
    }

    .policy-info-item em {
        color: #666;
        font-style: italic;
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

    .progress-bar {
        width: 100%;
        height: 8px;
        background: #e9ecef;
        border-radius: 4px;
        overflow: hidden;
        margin-top: 8px;
    }

    .progress-fill {
        height: 100%;
        background: #4c6ef5;
        transition: width 0.3s ease;
    }

    code {
        background: #f5f5f5;
        padding: 2px 6px;
        border-radius: 3px;
        font-family: "Courier New", monospace;
        font-size: 12px;
        color: #e03131;
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
        margin: 10% auto;
        padding: 30px;
        border: none;
        border-radius: 10px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
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
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-family: "Poppins", sans-serif;
        font-size: 13px;
        box-sizing: border-box;
    }

    .form-group input:focus,
    .form-group textarea:focus {
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

</style>

</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">

    <div class="header">
        <div>
            <h2>Data Policies</h2>
            <p>DLP policies, retention rules, and compliance settings</p>
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

    <!-- Data Classification Stats -->
    <div class="section-title">📊 Data Classification & Scanning</div>
    <div class="stats-grid">
        <div class="stat-card scan">
            <h4>📁 Total Objects</h4>
            <div class="value"><?php echo number_format($total_objects); ?></div>
            <div class="subtext">files in system</div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: 100%;"></div>
            </div>
        </div>

        <div class="stat-card scan">
            <h4>✅ Scanned Files</h4>
            <div class="value"><?php echo number_format($scanned_count); ?></div>
            <div class="subtext"><?php echo $scan_coverage; ?>% coverage</div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $scan_coverage; ?>%;"></div>
            </div>
        </div>

        <div class="stat-card scan">
            <h4>⏳ Unscanned Files</h4>
            <div class="value"><?php echo number_format($unscanned_count); ?></div>
            <div class="subtext">pending scan</div>
        </div>

        <div class="stat-card">
            <h4>🪣 Storage Buckets</h4>
            <div class="value"><?php echo $total_buckets; ?></div>
            <div class="subtext">managed buckets</div>
        </div>
    </div>

    <div class="info-box">
        <strong>📋 Scanning Policy</strong>
        All data is automatically scanned for sensitive information upon upload. Current scan coverage is at <?php echo $scan_coverage; ?>%.
    </div>

    <!-- Storage Policies -->
    <div class="section-title">💾 Storage & Retention Policies</div>
    <div class="stats-grid">
        <div class="stat-card retention">
            <h4>📅 Oldest File</h4>
            <div class="value"><?php echo ($retention['oldest_file'] ? date('M Y', strtotime($retention['oldest_file'])) : 'N/A'); ?></div>
            <div class="subtext">retention baseline</div>
        </div>

        <div class="stat-card retention">
            <h4>💾 Total Storage</h4>
            <div class="value"><?php echo formatBytes($retention['total_size']); ?></div>
            <div class="subtext">across all buckets</div>
        </div>

        <div class="stat-card retention">
            <h4>📊 Average File Size</h4>
            <div class="value"><?php echo formatBytes($size_stats['avg_size']); ?></div>
            <div class="subtext"><?php echo formatBytes($size_stats['max_size']); ?> max</div>
        </div>

        <div class="stat-card retention">
            <h4>🗂️ File Count</h4>
            <div class="value"><?php echo number_format($size_stats['total']); ?></div>
            <div class="subtext">total managed files</div>
        </div>
    </div>

    <div class="info-box">
        <strong>⏱️ Retention Policy</strong>
        Files are retained indefinitely unless explicitly deleted. Archived data is stored in cold storage (Tebi) after 90 days of inactivity.
    </div>

    <!-- Access Control Policies -->
    <div class="section-title">🔐 Access Control & Audit Policies</div>
    <div class="stats-grid">
        <div class="stat-card access">
            <h4>👥 Active Users</h4>
            <div class="value"><?php echo number_format($unique_users); ?></div>
            <div class="subtext">with access logs</div>
        </div>

        <div class="stat-card access">
            <h4>🌐 Unique IPs</h4>
            <div class="value"><?php echo number_format($unique_ips); ?></div>
            <div class="subtext">access sources</div>
        </div>

        <div class="stat-card access">
            <h4>📝 Audit Events</h4>
            <div class="value"><?php echo number_format($access_logs_count); ?></div>
            <div class="subtext">logged activities</div>
        </div>

        <div class="stat-card access">
            <h4>🔔 Events/User</h4>
            <div class="value"><?php echo $unique_users > 0 ? number_format(round($access_logs_count / $unique_users)) : '0'; ?></div>
            <div class="subtext">average per user</div>
        </div>
    </div>

    <div class="info-box">
        <strong>🛡️ Access Control Policy</strong>
        Role-based access control (RBAC) enforced. All access is logged with user, IP, timestamp, and action. Admin review required for deletions.
    </div>

    <!-- Storage Bucket Policies -->
    <div class="section-title">🪣 Storage Bucket Policies</div>
    <div class="policy-box">
        <div style="padding: 15px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f0f0f0;">
            <div>
                <h4 style="margin: 0 0 3px 0;">Manage Buckets</h4>
                <p style="margin: 0; color: #666; font-size: 12px;">Create, edit, or delete storage policies</p>
            </div>
            <button onclick="showCreatePolicy()" style="background: #4c6ef5; color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 13px;">➕ New Bucket</button>
        </div>
        
        <?php if (count($policies) > 0): ?>
            <?php foreach ($policies as $policy): ?>
                <div class="policy-item">
                    <div class="policy-info">
                        <h4>📦 <?php echo htmlspecialchars($policy['name']); ?></h4>
                        <p>Created: <strong><?php echo date('M d, Y', strtotime($policy['created_at'])); ?></strong></p>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <div class="policy-status active">Active</div>
                        <button onclick="editPolicy(<?php echo $policy['id']; ?>, '<?php echo htmlspecialchars($policy['name']); ?>')" style="background: #4dabf7; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 12px;">✏️ Edit</button>
                        <button onclick="deletePolicy(<?php echo $policy['id']; ?>, '<?php echo htmlspecialchars($policy['name']); ?>')" style="background: #ff6b6b; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 12px;">🗑️ Delete</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 40px 20px; color: #999;">
                <p style="margin: 0; font-size: 14px;">No buckets configured</p>
                <p style="margin: 5px 0 0 0; font-size: 12px;">Create one to get started</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- File Type Policies -->
    <div class="section-title">📄 File Type Policies (Top 15 Managed Types)</div>
    <table>
        <thead>
            <tr>
                <th>MIME Type</th>
                <th>File Count</th>
                <th>Policy Level</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($mime_types) > 0): ?>
                <?php foreach ($mime_types as $type): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($type['mime_type']); ?></code></td>
                        <td><?php echo number_format($type['count']); ?></td>
                        <td>
                            <?php 
                            $policy_level = 'standard';
                            if (strpos($type['mime_type'], 'pdf') !== false || strpos($type['mime_type'], 'word') !== false || strpos($type['mime_type'], 'sheet') !== false) {
                                $policy_level = 'sensitive';
                            } elseif (strpos($type['mime_type'], 'image') !== false) {
                                $policy_level = 'moderate';
                            }
                            ?>
                            <span class="type-badge"><?php echo ucfirst($policy_level); ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3">
                        <div class="empty-state">
                            <div class="empty-state-icon">📭</div>
                            <p>No file types found</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- DLP Policy Overview -->
    <div class="section-title">🛡️ DLP Policy Overview</div>
    <div class="policy-box">
        <div class="policy-item">
            <div class="policy-info">
                <h4>🔍 Data Classification</h4>
                <p>Automatic scanning on upload | Pattern matching for PII, PHI, and confidential data</p>
                <div class="policy-info-grid">
                    <div class="policy-info-item"><strong>Status:</strong> <em>Active</em></div>
                    <div class="policy-info-item"><strong>Scan Rate:</strong> <em><?php echo $scan_coverage; ?>%</em></div>
                    <div class="policy-info-item"><strong>Engine:</strong> <em>Custom + ML</em></div>
                </div>
            </div>
            <div class="policy-status active">Enabled</div>
        </div>

        <div class="policy-item">
            <div class="policy-info">
                <h4>⚡ Real-time Monitoring</h4>
                <p>Continuous audit logging | Alert generation on suspicious activity</p>
                <div class="policy-info-grid">
                    <div class="policy-info-item"><strong>Status:</strong> <em>Active</em></div>
                    <div class="policy-info-item"><strong>Log Retention:</strong> <em>180 days</em></div>
                    <div class="policy-info-item"><strong>Alert Level:</strong> <em>Immediate</em></div>
                </div>
            </div>
            <div class="policy-status active">Enabled</div>
        </div>

        <div class="policy-item">
            <div class="policy-info">
                <h4>🔐 Encryption</h4>
                <p>TLS in-transit | AES-256 at-rest encryption</p>
                <div class="policy-info-grid">
                    <div class="policy-info-item"><strong>Status:</strong> <em>Active</em></div>
                    <div class="policy-info-item"><strong>Algorithm:</strong> <em>AES-256-GCM</em></div>
                    <div class="policy-info-item"><strong>Key Mgmt:</strong> <em>Automatic</em></div>
                </div>
            </div>
            <div class="policy-status active">Enabled</div>
        </div>

        <div class="policy-item">
            <div class="policy-info">
                <h4>🚫 Deletion Policy</h4>
                <p>Admin approval required | 30-day soft delete with audit trail</p>
                <div class="policy-info-grid">
                    <div class="policy-info-item"><strong>Status:</strong> <em>Active</em></div>
                    <div class="policy-info-item"><strong>Approval:</strong> <em>Required</em></div>
                    <div class="policy-info-item"><strong>Recovery Window:</strong> <em>30 days</em></div>
                </div>
            </div>
            <div class="policy-status active">Enabled</div>
        </div>

        <div class="policy-item">
            <div class="policy-info">
                <h4>⛔ Compliance</h4>
                <p>GDPR, HIPAA, SOC 2 compliance tracking</p>
                <div class="policy-info-grid">
                    <div class="policy-info-item"><strong>Status:</strong> <em>Active</em></div>
                    <div class="policy-info-item"><strong>Standards:</strong> <em>GDPR, HIPAA</em></div>
                    <div class="policy-info-item"><strong>Audit:</strong> <em>Quarterly</em></div>
                </div>
            </div>
            <div class="policy-status review">Under Review</div>
        </div>
    </div>

</div>

<!-- Policy Modal -->
<div id="policyModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">New Bucket Policy</h3>
            <button class="close" onclick="closeModal()">&times;</button>
        </div>
        <form id="policyForm" method="POST" action="policies_action.php">
            <input type="hidden" id="formAction" name="action" value="create">
            <input type="hidden" id="policyId" name="policy_id">
            
            <div class="form-group">
                <label for="policyName">Bucket Name</label>
                <input type="text" id="policyName" name="name" required placeholder="e.g., Customer Data">
            </div>

            <div class="form-group">
                <label for="policyDesc">Description (Optional)</label>
                <textarea id="policyDesc" name="description" rows="3" placeholder="Policy details and retention rules..."></textarea>
            </div>

            <div class="form-buttons">
                <button type="submit" class="btn-submit">💾 Save Policy</button>
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function showCreatePolicy() {
    document.getElementById('modalTitle').textContent = 'Create New Bucket Policy';
    document.getElementById('formAction').value = 'create';
    document.getElementById('policyId').value = '';
    document.getElementById('policyName').value = '';
    document.getElementById('policyDesc').value = '';
    document.getElementById('policyModal').style.display = 'block';
}

function editPolicy(id, name) {
    document.getElementById('modalTitle').textContent = 'Edit Bucket Policy';
    document.getElementById('formAction').value = 'update';
    document.getElementById('policyId').value = id;
    document.getElementById('policyName').value = name;
    document.getElementById('policyModal').style.display = 'block';
}

function deletePolicy(id, name) {
    if (confirm('Are you sure you want to delete the "' + name + '" bucket? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'policies_action.php';
        form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="policy_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function closeModal() {
    document.getElementById('policyModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('policyModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

</body>
</html>
