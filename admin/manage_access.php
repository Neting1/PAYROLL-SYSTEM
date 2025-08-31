<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';
require_once '../includes/email.php';

requireAdmin();

$db = Database::getInstance();
$error = '';
$success = '';

// Get file ID from URL
$fileId = $_GET['id'] ?? null;

if (!$fileId || !is_numeric($fileId)) {
    showAlert('Invalid file ID.', 'danger');
    redirect('files.php');
}

// Get file information
$file = $db->fetch("SELECT * FROM payroll_files WHERE id = ? AND is_active = 1", [$fileId]);

if (!$file) {
    showAlert('File not found.', 'danger');
    redirect('files.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'grant_access') {
        $selectedUsers = $_POST['selected_users'] ?? [];
        
        if (!empty($selectedUsers)) {
            try {
                $db->beginTransaction();
                
                $newAccessCount = 0;
                $usersToNotify = [];
                
                foreach ($selectedUsers as $userId) {
                    // Check if access already exists
                    $existingAccess = $db->fetch(
                        "SELECT 1 FROM file_access WHERE file_id = ? AND user_id = ?", 
                        [$fileId, $userId]
                    );
                    
                    if (!$existingAccess) {
                        // Grant new access
                        grantFileAccess($fileId, $userId, $_SESSION['user_id']);
                        $newAccessCount++;
                        
                        // Get user details for email notification
                        $user = $db->fetch(
                            "SELECT id, email, full_name FROM users WHERE id = ? AND is_active = 1", 
                            [$userId]
                        );
                        
                        if ($user) {
                            $usersToNotify[] = $user;
                        }
                        
                        // Log the activity
                        $userName = $user['full_name'] ?? 'Unknown User';
                        logActivity($_SESSION['user_id'], 'access_granted', 
                                   "Granted access to file '{$file['title']}' for user: $userName");
                    }
                }
                
                $db->commit();
                
                // Send email notifications to newly granted users
                if (!empty($usersToNotify)) {
                    $fileInfo = [
                        'title' => $file['title'],
                        'description' => $file['description'],
                        'pay_period' => $file['pay_period']
                    ];
                    
                    $adminName = $_SESSION['full_name'];
                    $emailResults = sendBulkFileAccessNotifications($usersToNotify, $fileInfo, $adminName);
                    
                    // Log email activities
                    $emailSuccessCount = 0;
                    $emailFailureCount = 0;
                    
                    foreach ($emailResults as $result) {
                        logEmailActivity(
                            $result['user_id'], 
                            'file_access_notification', 
                            $result['email'], 
                            $result['success']
                        );
                        
                        if ($result['success']) {
                            $emailSuccessCount++;
                        } else {
                            $emailFailureCount++;
                        }
                    }
                    
                    // Create success message
                    if ($newAccessCount > 0) {
                        if ($emailSuccessCount > 0 && $emailFailureCount == 0) {
                            $success = "Access granted to $newAccessCount user(s) successfully! Email notifications sent to all users.";
                        } elseif ($emailSuccessCount > 0 && $emailFailureCount > 0) {
                            $success = "Access granted to $newAccessCount user(s)! Email notifications sent to $emailSuccessCount user(s). $emailFailureCount email(s) failed to send.";
                        } else {
                            $success = "Access granted to $newAccessCount user(s)! However, email notifications could not be sent.";
                        }
                    } else {
                        $success = "No new access granted (users may already have access).";
                    }
                } else {
                    if ($newAccessCount > 0) {
                        $success = "Access granted to $newAccessCount user(s) successfully!";
                    } else {
                        $success = "No new access granted (users may already have access).";
                    }
                }
                
            } catch (Exception $e) {
                $db->rollback();
                $error = 'Failed to grant access. Please try again.';
                error_log("File access grant error: " . $e->getMessage());
            }
        } else {
            $error = 'Please select at least one user.';
        }
    } elseif ($action === 'revoke_access') {
        $selectedUsers = $_POST['revoke_users'] ?? [];
        
        if (!empty($selectedUsers)) {
            try {
                $revokedCount = 0;
                foreach ($selectedUsers as $userId) {
                    if (revokeFileAccess($fileId, $userId)) {
                        $revokedCount++;
                        
                        // Get user name for logging
                        $user = $db->fetch("SELECT full_name FROM users WHERE id = ?", [$userId]);
                        $userName = $user['full_name'] ?? 'Unknown User';
                        
                        logActivity($_SESSION['user_id'], 'access_revoked', 
                                   "Revoked access to file '{$file['title']}' for user: $userName");
                    }
                }
                
                $success = "Access revoked for $revokedCount user(s) successfully!";
                
            } catch (Exception $e) {
                $error = 'Failed to revoke access. Please try again.';
                error_log("File access revoke error: " . $e->getMessage());
            }
        } else {
            $error = 'Please select at least one user to revoke access.';
        }
    }
}

// Get all users for access management
$allUsers = $db->fetchAll("SELECT id, username, full_name, employee_id, email FROM users WHERE role = 'user' AND is_active = 1 ORDER BY full_name");

// Get users who currently have access
$usersWithAccess = $db->fetchAll("
    SELECT u.id, u.username, u.full_name, u.employee_id, u.email, fa.granted_at, 
           granter.full_name as granted_by_name
    FROM file_access fa
    JOIN users u ON fa.user_id = u.id
    LEFT JOIN users granter ON fa.granted_by = granter.id
    WHERE fa.file_id = ? AND u.is_active = 1
    ORDER BY u.full_name
", [$fileId]);

// Get users who don't have access
$userIdsWithAccess = array_column($usersWithAccess, 'id');
$usersWithoutAccess = array_filter($allUsers, function($user) use ($userIdsWithAccess) {
    return !in_array($user['id'], $userIdsWithAccess);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage File Access - <?php echo APP_NAME; ?></title>
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
                <a class="nav-link active" href="files.php">
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
            <h1 class="h2">Manage File Access</h1>
            <a href="files.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Files
            </a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- File Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-file-earmark-pdf"></i> File Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Title:</strong> <?php echo htmlspecialchars($file['title']); ?></p>
                        <?php if ($file['pay_period']): ?>
                            <p><strong>Pay Period:</strong> <span class="badge bg-info"><?php echo htmlspecialchars($file['pay_period']); ?></span></p>
                        <?php endif; ?>
                        <?php if ($file['description']): ?>
                            <p><strong>Description:</strong> <?php echo htmlspecialchars($file['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <p><strong>File Size:</strong> <?php echo formatFileSize($file['file_size']); ?></p>
                        <p><strong>Upload Date:</strong> <?php echo formatDate($file['upload_date'], 'F j, Y \a\t g:i A'); ?></p>
                        <p><strong>Downloads:</strong> <?php echo $file['download_count']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Grant Access -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-person-plus"></i> Grant Access</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($usersWithoutAccess)): ?>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="grant_access">
                                
                                <p class="text-muted">Select users to grant access to this file:</p>
                                
                                <div class="mb-3">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllGrant">Select All</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="selectNoneGrant">Select None</button>
                                </div>
                                
                                <div class="row">
                                    <?php foreach ($usersWithoutAccess as $user): ?>
                                        <div class="col-12 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input grant-checkbox" type="checkbox" 
                                                       name="selected_users[]" value="<?php echo $user['id']; ?>" 
                                                       id="grant_user_<?php echo $user['id']; ?>">
                                                <label class="form-check-label" for="grant_user_<?php echo $user['id']; ?>">
                                                    <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                                    <br><small class="text-muted">
                                                        <?php echo htmlspecialchars($user['email']); ?>
                                                        <?php if ($user['employee_id']): ?>
                                                            • ID: <?php echo htmlspecialchars($user['employee_id']); ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    <strong>Email Notifications:</strong> Users will automatically receive email notifications when granted access to this file.
                                </div>
                                
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-circle"></i> Grant Access & Send Notifications
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="bi bi-check-all fs-1 text-success"></i>
                                <p class="text-muted mt-2">All users already have access to this file.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Current Access -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-people"></i> Current Access (<?php echo count($usersWithAccess); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($usersWithAccess)): ?>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="revoke_access">
                                
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <tbody>
                                            <?php foreach ($usersWithAccess as $user): ?>
                                                <tr>
                                                    <td style="width: 30px;">
                                                        <input class="form-check-input revoke-checkbox" type="checkbox" 
                                                               name="revoke_users[]" value="<?php echo $user['id']; ?>" 
                                                               id="revoke_user_<?php echo $user['id']; ?>">
                                                    </td>
                                                    <td>
                                                        <label class="form-check-label" for="revoke_user_<?php echo $user['id']; ?>">
                                                            <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                                            <br><small class="text-muted">
                                                                <?php echo htmlspecialchars($user['email']); ?>
                                                                <?php if ($user['employee_id']): ?>
                                                                    • ID: <?php echo htmlspecialchars($user['employee_id']); ?>
                                                                <?php endif; ?>
                                                            </small>
                                                            <br><small class="text-success">
                                                                Access granted: <?php echo formatDate($user['granted_at'], 'M j, Y'); ?>
                                                                <?php if ($user['granted_by_name']): ?>
                                                                    by <?php echo htmlspecialchars($user['granted_by_name']); ?>
                                                                <?php endif; ?>
                                                            </small>
                                                        </label>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="mt-3">
                                    <button type="button" class="btn btn-sm btn-outline-danger" id="selectAllRevoke">Select All</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="selectNoneRevoke">Select None</button>
                                </div>
                                
                                <div class="mt-3">
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to revoke access for selected users?')">
                                        <i class="bi bi-person-dash"></i> Revoke Selected
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="bi bi-person-x fs-1 text-muted"></i>
                                <p class="text-muted mt-2">No users currently have access to this file.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Grant access checkboxes
        document.getElementById('selectAllGrant')?.addEventListener('click', function() {
            document.querySelectorAll('.grant-checkbox').forEach(checkbox => {
                checkbox.checked = true;
            });
        });
        
        document.getElementById('selectNoneGrant')?.addEventListener('click', function() {
            document.querySelectorAll('.grant-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
        });

        // Revoke access checkboxes
        document.getElementById('selectAllRevoke')?.addEventListener('click', function() {
            document.querySelectorAll('.revoke-checkbox').forEach(checkbox => {
                checkbox.checked = true;
            });
        });
        
        document.getElementById('selectNoneRevoke')?.addEventListener('click', function() {
            document.querySelectorAll('.revoke-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
        });
    </script>
</body>
</html>
