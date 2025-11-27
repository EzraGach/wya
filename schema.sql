-- DLP System Database Schema

-- Create buckets table to store bucket metadata
CREATE TABLE IF NOT EXISTS buckets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    description VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE
);

-- Create objects table to store file metadata
CREATE TABLE IF NOT EXISTS objects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bucket_id INT NOT NULL,
    file_key VARCHAR(500) NOT NULL,
    file_path VARCHAR(500),
    file_size BIGINT,
    mime_type VARCHAR(100),
    storage_type ENUM('local', 'tebi') DEFAULT 'local',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INT,
    is_scanned BOOLEAN DEFAULT FALSE,
    scan_results TEXT,
    hash VARCHAR(64),
    UNIQUE KEY unique_bucket_key (bucket_id, file_key),
    FOREIGN KEY (bucket_id) REFERENCES buckets(id) ON DELETE CASCADE,
    INDEX (uploaded_at),
    INDEX (is_scanned)
);

-- Create audit log table for DLP tracking
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    object_id INT,
    action VARCHAR(100),
    user_id INT,
    ip_address VARCHAR(45),
    details TEXT,
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (object_id) REFERENCES objects(id) ON DELETE CASCADE,
    INDEX (logged_at)
);

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    role ENUM('admin', 'user', 'auditor') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- Create system settings table
CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(255) UNIQUE NOT NULL,
    value LONGTEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (setting_key)
);

-- Insert default bucket
INSERT IGNORE INTO buckets (id, name, description) VALUES (1, 'dlp', 'Default DLP bucket');

-- Insert admin user (password: admin123 - change this!)
INSERT IGNORE INTO users (id, username, password, email, role) VALUES (1, 'admin', '$2y$10$YourHashedPasswordHere', 'admin@dlp.local', 'admin');

-- Insert default system settings
INSERT IGNORE INTO system_settings (setting_key, value) VALUES 
('app_name', 'DLP System'),
('app_version', '1.0.0'),
('timezone', 'UTC'),
('scan_interval', '24'),
('max_file_size', '1073741824'),
('enable_notifications', '1'),
('notification_email', ''),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_user', ''),
('enable_backup', '1'),
('backup_frequency', 'daily'),
('enable_audit_log', '1'),
('audit_retention_days', '180'),
('session_timeout', '3600'),
('enable_2fa', '0'),
('password_min_length', '8'),
('password_require_uppercase', '1'),
('password_require_numbers', '1'),
('password_require_special', '1'),
('password_expiration_days', '90');
