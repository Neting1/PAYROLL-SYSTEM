<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Default XAMPP password is empty
define('DB_NAME', 'payroll_system');

// Application configuration
define('APP_NAME', 'Payroll Management System');
define('BASE_URL', 'http://localhost/payroll_system/');
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['pdf']);

// Security configuration
define('SESSION_LIFETIME', 3600); // 1 hour
define('SALT', 'your_secret_salt_here_change_this_in_production');

// Email Configuration
define('SYSTEM_NAME', 'Twinhill Payroll System');
define('SYSTEM_EMAIL', 'noreply@twinhill.xeonsys.com');
define('ADMIN_EMAIL', 'admin@twinhill.xeonsys.com');

// SMTP Configuration
define('SMTP_HOST', 'localhost');
define('SMTP_AUTH', true);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_SECURE', 'tls');
define('SMTP_PORT', 587);

// Password reset configuration
define('PASSWORD_RESET_EXPIRY', 3600); // 1 hour
define('EMAIL_FROM', 'noreply@yourdomain.com');
define('EMAIL_FROM_NAME', 'Twinhill Payroll System');

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>