# Payroll Management System

A secure web-based payroll management system built with PHP and MySQL, designed for XAMPP hosting. The system allows administrators to upload PDF payroll files and manage user access, while employees can securely view and download their payroll documents.

## Features

### Admin Features
- **File Upload**: Upload PDF payroll files with metadata (title, description, pay period)
- **User Management**: Control which users have access to specific files
- **File Management**: View, download, and delete payroll files
- **Activity Logging**: Track all system activities and file downloads
- **Dashboard**: Overview of files, users, and download statistics

### User Features
- **Secure Access**: Role-based authentication system
- **File Viewing**: View PDF files directly in the browser
- **File Downloads**: Download payroll files securely
- **Dashboard**: Personal overview of available files and download history

### Security Features
- **CSRF Protection**: Protection against cross-site request forgery attacks
- **Session Management**: Secure session handling with timeout
- **File Access Control**: Granular permissions for file access
- **Activity Logging**: Comprehensive audit trail
- **Input Sanitization**: Protection against XSS and SQL injection

## System Requirements

- **Web Server**: Apache (included in XAMPP)
- **PHP**: Version 7.4 or higher
- **Database**: MySQL 5.7 or higher
- **Browser**: Modern web browser with JavaScript enabled

## Installation Instructions

### 1. Setup XAMPP
1. Download and install [XAMPP](https://www.apachefriends.org/)
2. Start Apache and MySQL services from XAMPP Control Panel

### 2. Setup Database
1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Create a new database or import the provided SQL file:
   ```sql
   -- Run the contents of database_setup.sql
   ```
3. The SQL file will create all necessary tables and default users

### 3. Deploy Application
1. Copy the `payroll_system` folder to your XAMPP `htdocs` directory
   ```
   C:\xampp\htdocs\payroll_system\
   ```

### 4. Configure Application
1. Open `includes/config.php`
2. Update database credentials if needed:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', ''); // Default XAMPP password is empty
   define('DB_NAME', 'payroll_system');
   ```
3. Update the base URL if needed:
   ```php
   define('BASE_URL', 'http://localhost/payroll_system/');
   ```

### 5. Set File Permissions
1. Ensure the `uploads` directory is writable:
   ```
   chmod 755 uploads/
   ```

### 6. Access the System
1. Open your web browser
2. Navigate to: `http://localhost/payroll_system/`
3. You will be redirected to the login page

## Default Login Credentials

### Administrator Account
- **Username**: admin
- **Password**: admin123
- **Access**: Full system access, file upload, user management

### User Accounts
- **Username**: john_doe | **Password**: user123 | **Name**: John Doe
- **Username**: jane_smith | **Password**: user123 | **Name**: Jane Smith

## Directory Structure

```
payroll_system/
├── admin/                  # Admin interface files
│   ├── dashboard.php      # Admin dashboard
│   ├── upload.php         # File upload interface
│   ├── files.php          # File management
│   └── users.php          # User management
├── user/                  # User interface files
│   ├── dashboard.php      # User dashboard
│   ├── download.php       # File download handler
│   └── view.php           # File viewer
├── includes/              # Core system files
│   ├── config.php         # Configuration settings
│   ├── database.php       # Database connection class
│   └── functions.php      # Utility functions
├── api/                   # API endpoints
│   └── file_access.php    # File access management API
├── css/                   # Stylesheets
│   └── style.css          # Main stylesheet
├── uploads/               # File upload directory
├── assets/                # Static assets
├── js/                    # JavaScript files
├── database_setup.sql     # Database schema
├── index.php              # Main entry point
├── login.php              # Login page
├── logout.php             # Logout handler
└── README.md              # This file
```

## Usage Guide

### For Administrators

1. **Upload Files**:
   - Navigate to "Upload Files" in the admin panel
   - Fill in file details (title, description, pay period)
   - Select PDF file to upload
   - Choose which users should have access
   - Click "Upload File"

2. **Manage File Access**:
   - Go to "Manage Files"
   - Click the people icon next to any file
   - Grant or revoke access for specific users
   - Use "Grant All" or "Revoke All" for bulk operations

3. **View Activity Logs**:
   - Access "Activity Logs" to see all system activities
   - Monitor file downloads and user actions
   - Track login/logout events

### For Users

1. **View Available Files**:
   - Login to access your dashboard
   - View all files you have access to
   - See file details, upload dates, and download counts

2. **Download Files**:
   - Click "Download" to save files to your computer
   - Click "View" to open files in your browser

## Security Considerations

### Production Deployment
1. **Change Default Passwords**: Update all default account passwords
2. **Database Security**: Create a dedicated database user with limited privileges
3. **HTTPS**: Enable HTTPS for secure data transmission
4. **File Validation**: Only PDF files are allowed by default
5. **Directory Protection**: Secure the uploads directory from direct access

### Backup Strategy
1. **Database**: Regular MySQL database backups
2. **Files**: Backup the uploads directory regularly
3. **Configuration**: Keep backups of configuration files

## Troubleshooting

### Common Issues

1. **Database Connection Error**:
   - Verify MySQL service is running
   - Check database credentials in config.php
   - Ensure database exists and tables are created

2. **File Upload Issues**:
   - Check PHP upload_max_filesize setting
   - Verify uploads directory permissions
   - Ensure disk space is available

3. **Permission Denied**:
   - Check file system permissions
   - Verify web server user has access to files

4. **Session Issues**:
   - Clear browser cache and cookies
   - Check PHP session configuration
   - Verify session directory is writable

### Log Files
- **PHP Errors**: Check Apache error logs
- **System Activities**: View activity logs in admin panel
- **Database Errors**: Enable MySQL query log for debugging

## Customization

### Adding Features
1. **New User Roles**: Extend the role system in the database
2. **File Types**: Modify allowed file extensions in config.php
3. **Email Notifications**: Add email functionality for file uploads
4. **Advanced Reporting**: Create custom reports and analytics

### Styling
- **CSS**: Modify `css/style.css` for custom styling
- **Bootstrap**: The system uses Bootstrap 5 for responsive design
- **Icons**: Bootstrap Icons are included for UI elements

## Support

For technical support or feature requests, please review the code comments and database schema. The system is designed to be self-contained and well-documented.

## Security Notice

This system includes basic security measures but should be thoroughly tested and hardened before production use. Consider implementing additional security measures such as:
- Rate limiting
- Advanced logging
- File scanning
- Database encryption
- Regular security updates

---

**Version**: 1.0  
**Last Updated**: 2024  
**Technology Stack**: PHP, MySQL, Bootstrap 5, JavaScript
