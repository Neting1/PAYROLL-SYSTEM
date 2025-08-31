<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once '../includes/email.php';

requireAdmin();

$db = Database::getInstance();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $testEmail = sanitizeInput($_POST['test_email'] ?? '');
    
    if (empty($testEmail)) {
        $message = 'Please enter an email address.';
        $messageType = 'danger';
    } elseif (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'danger';
    } else {
        // Send test email
        $success = sendTestEmail($testEmail);
        
        if ($success) {
            $message = "Test email sent successfully to $testEmail! Check your inbox.";
            $messageType = 'success';
            
            // Log the test
            logActivity($_SESSION['user_id'], 'email_test', "Test email sent to: $testEmail");
        } else {
            $message = "Failed to send test email to $testEmail. Please check your email configuration.";
            $messageType = 'danger';
            
            // Log the failed test
            logActivity($_SESSION['user_id'], 'email_test_failed', "Failed to send test email to: $testEmail");
        }
    }
}

// Get current email configuration status
$emailStatus = EMAIL_ENABLED ? 'Enabled' : 'Disabled';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email System - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><?php echo APP_NAME; ?> - Admin</a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar Navigation -->
    <nav class="sidebar">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="bi bi-house"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="upload.php">
                    <i class="bi bi-cloud-upload"></i> Upload Files
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="files.php">
                    <i class="bi bi-files"></i> Manage Files
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="users.php">
                    <i class="bi bi-people"></i> Manage Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logs.php">
                    <i class="bi bi-list-ul"></i> Activity Logs
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Email System Test</h1>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-envelope"></i> Send Test Email</h5>
                    </div>
                    <div class="card-body">
                        <p>Use this tool to test if email notifications are working correctly. A test email will be sent to the specified address.</p>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="mb-3">
                                <label for="test_email" class="form-label">Test Email Address</label>
                                <input type="email" class="form-control" id="test_email" name="test_email" 
                                       placeholder="Enter email address to test" required>
                                <div class="form-text">Enter a valid email address where you can receive test messages</div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send"></i> Send Test Email
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-gear"></i> Email Configuration</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td>
                                    <span class="badge bg-<?php echo EMAIL_ENABLED ? 'success' : 'secondary'; ?>">
                                        <?php echo $emailStatus; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>SMTP Host:</strong></td>
                                <td><code><?php echo htmlspecialchars(SMTP_HOST); ?></code></td>
                            </tr>
                            <tr>
                                <td><strong>SMTP Port:</strong></td>
                                <td><code><?php echo SMTP_PORT; ?></code></td>
                            </tr>
                            <tr>
                                <td><strong>From Email:</strong></td>
                                <td><code><?php echo htmlspecialchars(FROM_EMAIL); ?></code></td>
                            </tr>
                            <tr>
                                <td><strong>From Name:</strong></td>
                                <td><?php echo htmlspecialchars(FROM_NAME); ?></td>
                            </tr>
                        </table>
                        
                        <?php if (!EMAIL_ENABLED): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                <strong>Email notifications are disabled.</strong><br>
                                Update the configuration in <code>includes/config.php</code> to enable email notifications.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <h6><i class="bi bi-info-circle"></i> Email Configuration Guide</h6>
                    </div>
                    <div class="card-body">
                        <small>
                            <h6>Local Testing (XAMPP):</h6>
                            <ul class="mb-2">
                                <li>SMTP Host: <code>localhost</code></li>
                                <li>SMTP Port: <code>25</code></li>
                                <li>No username/password needed</li>
                            </ul>
                            
                            <h6>Gmail SMTP:</h6>
                            <ul class="mb-2">
                                <li>SMTP Host: <code>smtp.gmail.com</code></li>
                                <li>SMTP Port: <code>587</code></li>
                                <li>Encryption: <code>tls</code></li>
                                <li>Use App Password for authentication</li>
                            </ul>
                            
                            <p class="text-muted">
                                <strong>Note:</strong> For production use, configure proper SMTP settings in <code>includes/config.php</code>.
                            </p>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-question-circle"></i> Email Notification Features</h5>
                    </div>
                    <div class="card-body">
                        <h6>When are emails sent?</h6>
                        <ul>
                            <li><strong>File Upload:</strong> When administrators upload files and grant access to specific users</li>
                            <li><strong>Access Granted:</strong> When administrators grant access to existing files through the file management page</li>
                            <li><strong>User Registration:</strong> Activity logged when new users register (for admin awareness)</li>
                        </ul>
                        
                        <h6>Email Content:</h6>
                        <ul>
                            <li>Professional HTML-formatted emails with system branding</li>
                            <li>File details including title, description, and pay period</li>
                            <li>Direct link to user dashboard for easy access</li>
                            <li>Clear instructions on how to access the shared files</li>
                        </ul>
                        
                        <h6>Activity Logging:</h6>
                        <ul>
                            <li>All email notifications are logged in the activity logs</li>
                            <li>Success and failure status is tracked</li>
                            <li>Administrators can monitor email delivery in the logs</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
