<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'database.php';

// Get current user info
$user = $conn->query("SELECT username, email FROM users WHERE id = " . $_SESSION['user_id'])->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DLP - Change Password</title>

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

    .password-container {
        background: white;
        padding: 40px;
        border-radius: 10px;
        box-shadow: 0px 2px 8px rgba(0,0,0,0.08);
        max-width: 500px;
    }

    .user-info {
        background: #f5f6fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 25px;
        border-left: 4px solid #4c6ef5;
    }

    .user-info p {
        margin: 5px 0;
        color: #666;
        font-size: 13px;
    }

    .user-info strong {
        color: #1a1a1a;
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

    .form-group input {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-family: "Poppins", sans-serif;
        font-size: 13px;
        box-sizing: border-box;
    }

    .form-group input:focus {
        outline: none;
        border-color: #4c6ef5;
        box-shadow: 0 0 0 3px rgba(76, 110, 245, 0.1);
    }

    .password-strength {
        margin-top: 8px;
        height: 6px;
        background: #e9ecef;
        border-radius: 3px;
        overflow: hidden;
    }

    .password-strength-bar {
        height: 100%;
        width: 0%;
        background: #ff6b6b;
        transition: width 0.3s ease, background-color 0.3s ease;
    }

    .password-strength.weak .password-strength-bar {
        width: 33%;
        background: #ff6b6b;
    }

    .password-strength.fair .password-strength-bar {
        width: 66%;
        background: #ffd43b;
    }

    .password-strength.good .password-strength-bar {
        width: 100%;
        background: #51cf66;
    }

    .strength-text {
        font-size: 11px;
        margin-top: 5px;
        color: #666;
    }

    .requirements {
        background: #f5f6fa;
        padding: 15px;
        border-radius: 6px;
        margin-bottom: 20px;
        font-size: 12px;
    }

    .requirements h4 {
        margin: 0 0 10px 0;
        color: #333;
        font-weight: 600;
    }

    .requirement-item {
        display: flex;
        align-items: center;
        margin-bottom: 8px;
        color: #666;
    }

    .requirement-item:last-child {
        margin-bottom: 0;
    }

    .requirement-icon {
        display: inline-block;
        width: 20px;
        height: 20px;
        margin-right: 10px;
        border-radius: 50%;
        background: #e9ecef;
        color: white;
        text-align: center;
        line-height: 20px;
        font-size: 11px;
        font-weight: bold;
    }

    .requirement-item.met .requirement-icon {
        background: #51cf66;
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

    .error-box {
        background: #ffe3e3;
        border-left: 4px solid #ff6b6b;
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        color: #c92a2a;
    }

    .error-box strong {
        display: block;
        margin-bottom: 5px;
    }

</style>

</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main">

    <div class="header">
        <div>
            <h2>🔐 Change Password</h2>
            <p>Update your account password securely</p>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="info-box">
            <strong>✅ Success</strong>
            <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="error-box">
            <strong>❌ Error</strong>
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <div class="password-container">
        <div class="user-info">
            <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
        </div>

        <form method="POST" action="change_password_action.php">
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" required placeholder="Enter your current password">
            </div>

            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required placeholder="Enter new password" onkeyup="checkPasswordStrength()">
                <div class="password-strength" id="strengthBar">
                    <div class="password-strength-bar"></div>
                </div>
                <div class="strength-text" id="strengthText">Enter a strong password</div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm new password">
            </div>

            <div class="requirements">
                <h4>Password Requirements</h4>
                <div class="requirement-item" id="req-length">
                    <div class="requirement-icon">✓</div>
                    <span>At least 8 characters</span>
                </div>
                <div class="requirement-item" id="req-uppercase">
                    <div class="requirement-icon">✓</div>
                    <span>At least one uppercase letter (A-Z)</span>
                </div>
                <div class="requirement-item" id="req-lowercase">
                    <div class="requirement-icon">✓</div>
                    <span>At least one lowercase letter (a-z)</span>
                </div>
                <div class="requirement-item" id="req-number">
                    <div class="requirement-icon">✓</div>
                    <span>At least one number (0-9)</span>
                </div>
                <div class="requirement-item" id="req-special">
                    <div class="requirement-icon">✓</div>
                    <span>At least one special character (!@#$%^&*)</span>
                </div>
                <div class="requirement-item" id="req-match">
                    <div class="requirement-icon">✓</div>
                    <span>Passwords match</span>
                </div>
            </div>

            <div class="form-buttons">
                <button type="submit" class="btn-submit" id="submitBtn" disabled>🔒 Update Password</button>
                <a href="dashboard.php" style="flex: 1;"><button type="button" class="btn-cancel" style="width: 100%;">Cancel</button></a>
            </div>
        </form>
    </div>

</div>

<script>
function checkPasswordStrength() {
    const password = document.getElementById('new_password').value;
    const confirm = document.getElementById('confirm_password').value;
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    const submitBtn = document.getElementById('submitBtn');

    let strength = 0;
    let met = [];

    // Length check
    const hasLength = password.length >= 8;
    document.getElementById('req-length').classList.toggle('met', hasLength);
    if (hasLength) met.push('length');

    // Uppercase check
    const hasUpper = /[A-Z]/.test(password);
    document.getElementById('req-uppercase').classList.toggle('met', hasUpper);
    if (hasUpper) met.push('upper');

    // Lowercase check
    const hasLower = /[a-z]/.test(password);
    document.getElementById('req-lowercase').classList.toggle('met', hasLower);
    if (hasLower) met.push('lower');

    // Number check
    const hasNumber = /[0-9]/.test(password);
    document.getElementById('req-number').classList.toggle('met', hasNumber);
    if (hasNumber) met.push('number');

    // Special char check
    const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
    document.getElementById('req-special').classList.toggle('met', hasSpecial);
    if (hasSpecial) met.push('special');

    // Match check
    const matches = password === confirm && password.length > 0;
    document.getElementById('req-match').classList.toggle('met', matches);

    // Update strength bar
    strength = met.length;
    if (strength <= 2) {
        strengthBar.classList.remove('fair', 'good');
        strengthBar.classList.add('weak');
        strengthText.textContent = 'Weak password';
    } else if (strength <= 4) {
        strengthBar.classList.remove('weak', 'good');
        strengthBar.classList.add('fair');
        strengthText.textContent = 'Fair password';
    } else {
        strengthBar.classList.remove('weak', 'fair');
        strengthBar.classList.add('good');
        strengthText.textContent = 'Strong password';
    }

    // Enable submit if all requirements met
    submitBtn.disabled = !(hasLength && hasUpper && hasLower && hasNumber && hasSpecial && matches);
}

// Check password strength on confirm field change too
document.getElementById('confirm_password').addEventListener('keyup', checkPasswordStrength);

// Prevent form submission if passwords don't match
document.querySelector('form').addEventListener('submit', function(e) {
    const password = document.getElementById('new_password').value;
    const confirm = document.getElementById('confirm_password').value;
    
    if (password !== confirm) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
    }
});
</script>

</body>
</html>
