# DLP System (Data Loss Prevention)

A comprehensive Data Loss Prevention system built with PHP, featuring file scanning, storage management, and audit logging.

## Features

- **File Management**: Upload and manage files across multiple buckets
- **Hybrid Storage**: Support for local storage and Tebi S3 (or any S3-compatible service)
- **Database Tracking**: Full metadata tracking of all files with SQL database
- **User Authentication**: Secure login system with role-based access
- **Audit Logging**: Complete audit trail of all file operations
- **Scan Management**: Mark files as scanned and store scan results
- **Dashboard**: Overview of file statistics and system status

## System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                   Web Interface (PHP)                       │
├─────────────────────────────────────────────────────────────┤
│              Storage Adapter Layer                          │
│  (Handles local storage and Tebi S3 integration)           │
├─────────────────────────────────────────────────────────────┤
│  ┌──────────────────────┐      ┌──────────────────────┐   │
│  │  Local File Storage  │      │  Tebi S3 Storage     │   │
│  │  (storage/files/)    │      │  (Optional)          │   │
│  └──────────────────────┘      └──────────────────────┘   │
├─────────────────────────────────────────────────────────────┤
│              MySQL Database                                 │
│  (Buckets, Objects, Metadata, Audit Logs, Users)          │
└─────────────────────────────────────────────────────────────┘
```

## Requirements

- PHP 7.4+
- MySQL 5.7+ / MariaDB
- Composer (for AWS SDK)
- 50MB free disk space minimum

## Installation

### Option 1: Docker Compose (Recommended - Easiest)

```bash
# Clone repository
git clone <repository-url> dlp-system
cd dlp-system

# Start services
docker-compose up -d

# Wait for MySQL to initialize (30 seconds)
sleep 30

# Access the application
# Open http://localhost:8080 in your browser
```

**Login credentials:**
- Username: `admin`
- Password: `admin123`

Stop services:
```bash
docker-compose down
```

View logs:
```bash
docker-compose logs -f php
docker-compose logs -f mysql
```

### Option 2: Local PHP + MySQL Setup

#### 1. Clone the repository
```bash
git clone <repository-url> dlp-system
cd dlp-system
```

#### 2. Install dependencies
```bash
composer install
```

#### 3. Setup environment
```bash
cp .env.example .env
# Edit .env with your database credentials
nano .env
```

**Example .env for local development:**
```env
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=dlp_db
```

#### 4. Initialize database
```bash
# Using PHP setup script
php setup.php

# OR manually
mysql -u root -p dlp_db < schema.sql
```

#### 5. Start both MySQL and PHP
```bash
# Make start script executable
chmod +x start.sh

# Run both services
./start.sh

# OR start them separately:
# Terminal 1:
sudo service mysql start

# Terminal 2:
php -S localhost:8080
```

Access the application at http://localhost:8080

### Option 3: Manual Setup

## Configuration

### Environment Variables (.env)

```env
# Database
DB_HOST=localhost
DB_USER=root
DB_PASS=your_password
DB_NAME=dlp_db

# Tebi S3 Storage (optional)
ENABLE_TEBI_STORAGE=false
TEBI_KEY=your_tebi_key
TEBI_SECRET=your_tebi_secret
TEBI_ENDPOINT=https://s3.tebi.io
TEBI_REGION=de

# Application
APP_DEBUG=false
SESSION_TIMEOUT=3600
```

### Storage Modes

**Local Storage (Default)**
- Files stored in `storage/files/`
- No external dependencies
- Good for development and testing
- Set `ENABLE_TEBI_STORAGE=false` in `.env`

**Tebi S3 Storage**
- Files stored in Tebi cloud bucket
- Scalable for production
- Requires Tebi account and credentials
- Set `ENABLE_TEBI_STORAGE=true` in `.env`

## Usage

### Login
1. Navigate to http://localhost:8000/login.php
2. Default credentials:
   - Username: `admin`
   - Password: `admin123`

**⚠️ Change the admin password immediately after first login!**

### File Upload
1. Navigate to "File Scanning" in the sidebar
2. Select a bucket from the dropdown
3. Choose a file to upload
4. Click "Upload and Scan"

### View Files
1. Click on a bucket name to view its contents
2. See file metadata including:
   - File name and size
   - MIME type
   - Upload timestamp
   - Scan status

### Monitor Activity
- **Access Logs**: View all file access events
- **Audit Logs**: Track all operations with timestamps
- **Reports**: Generate compliance reports

## File Structure

```
dlp-system/
├── config.php              # Application configuration
├── database.php            # Database connection and helpers
├── storage_adapter.php     # File storage abstraction layer
├── tebi_config.php         # Tebi S3 configuration
├── tebi_functions.php      # Legacy Tebi functions (deprecated)
├── schema.sql              # Database schema
├── setup.php               # Setup script
├── .env.example            # Environment variables template
├── .gitignore              # Git ignore rules
│
├── Pages:
├── index.php               # Homepage
├── login.php               # Login page
├── logout.php              # Logout handler
├── dashboard.php           # Main dashboard
├── scan.php                # File scanning/upload
├── data_monitoring.php     # Data monitoring
├── monitoring.php          # System monitoring
├── access_logs.php         # Access logs view
├── alerts.php              # Threat alerts
├── policies.php            # DLP policies
├── users.php               # User management
├── reports.php             # Reports view
├── settings.php            # Application settings
│
├── style.css               # Global styles
├── sidebar.php             # Sidebar component
│
├── storage/files/          # Local file storage directory
├── logs/                   # Application logs
└── vendor/                 # Composer dependencies
```

## Database Schema

### Tables

**buckets**
- Stores bucket configurations
- Links to objects

**objects**
- File metadata and references
- Tracks storage location (local or Tebi)
- Scan status and results
- File hash for integrity checking

**audit_logs**
- Complete audit trail
- User, action, timestamp, IP address
- Operation details

**users**
- User authentication
- Role-based access (admin, user, auditor)

## API Functions

### Storage Adapter

```php
// Upload file with metadata tracking
uploadFile($bucket_name, $key, $tmp_path, $user_id);

// List all buckets
listBuckets();

// List files in bucket
listObjects($bucket_name);

// Download file
downloadFile($object_id);

// Delete file
deleteFile($object_id, $user_id);

// Get file metadata
getFileMetadata($object_id);
```

### Database Functions

```php
// Get bucket ID
getBucketId($bucket_name);

// Get all buckets
getAllBuckets();

// Get objects by bucket
getObjectsByBucket($bucket_id);

// Insert object record
insertObject($bucket_id, $key, $file_path, $file_size, $mime_type, $storage_type, $uploaded_by);

// Log audit entry
logAudit($object_id, $action, $user_id, $ip_address, $details);

// Update scan status
updateScanStatus($object_id, $is_scanned, $scan_results);
```

## Security Considerations

1. **Credentials**: Never commit `.env` file with real credentials
2. **Authentication**: Always use HTTPS in production
3. **File Upload**: Implement file type validation
4. **Access Control**: Verify user permissions before operations
5. **Database**: Use prepared statements (all functions do this)
6. **Audit Logging**: Monitor all file access and modifications

## Troubleshooting

### Database Connection Error
```
Error: Database connection failed
```
- Check MySQL is running: `mysql -u root -p`
- Verify .env credentials
- Ensure database exists: `CREATE DATABASE dlp_db;`

### File Upload Permission Error
```
Error: Failed to move uploaded file
```
- Check storage/files/ directory permissions: `chmod -R 755 storage/`
- Ensure PHP has write permissions

### Schema Import Errors
```
Error during schema import
```
- Manually import: `mysql -u root -p dlp_db < schema.sql`
- Check MySQL version (5.7+)
- Verify UTF-8 encoding

### Tebi Connection Issues
- Verify TEBI_KEY and TEBI_SECRET in .env
- Check endpoint URL is correct
- Ensure Tebi bucket exists

## Development

### Adding New Features

1. **New Page**: Create `new_feature.php`, add navigation to `sidebar.php`
2. **Database Changes**: Update `schema.sql` and create migration
3. **Storage Functions**: Add to `storage_adapter.php`
4. **Styling**: Update `style.css`

### Testing

```bash
# Test database connection
php -r "require 'database.php'; echo 'Connected!';"

# Test file upload
php -r "require 'storage_adapter.php'; var_dump(listBuckets());"

# Test Tebi connection
php -r "require 'tebi_config.php'; echo \$s3Client ? 'Connected!' : 'Not configured';"
```

## Production Deployment

1. Use HTTPS
2. Set `APP_DEBUG=false` in .env
3. Use strong admin password
4. Configure Tebi S3 for file storage
5. Set up automated backups
6. Enable audit logging
7. Configure log rotation
8. Use environment-specific configurations
9. Set up error monitoring (e.g., Sentry)
10. Implement rate limiting

## License

Proprietary - All rights reserved

## Support

For issues or questions, please contact the development team.

## Changelog

### Version 1.0.0
- Initial release
- Local and Tebi S3 storage support
- User authentication and role-based access
- Audit logging
- File management interface
