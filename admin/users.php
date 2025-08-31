<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

requireAdmin();

$db = Database::getInstance();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_user':
            $username = sanitizeInput($_POST['username'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $fullName = sanitizeInput($_POST['full_name'] ?? '');
            $employeeId = sanitizeInput($_POST['employee_id'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'user';
            
            if (empty($username) || empty($email) || empty($fullName) || empty($password)) {
                showAlert('Please fill in all required fields.', 'danger');
            } elseif (strlen($password) < 6) {
                showAlert('Password must be at least 6 characters long.', 'danger');
            } else {
                // Check if username or email already exists
                $existing = $db->fetch("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
                
                if ($existing) {
                    showAlert('Username or email already exists.', 'danger');
                } else {
                    try {
                        $passwordHash = hashPassword($password);
                        $sql = "INSERT INTO users (username, email, password_hash, full_name, employee_id, role) VALUES (?, ?, ?, ?, ?, ?)";
                        $db->execute($sql, [$username, $email, $passwordHash, $fullName, $employeeId ?: null, $role]);
                        
                        logActivity($_SESSION['user_id'], 'user_created', "Created user: $username ($fullName)");
                        showAlert('User created successfully!', 'success');
                    } catch (Exception $e) {
                        showAlert('Failed to create user. Please try again.', 'danger');
                        error_log("User creation error: " . $e->getMessage());
                    }
                }
            }
            break;
            
        case 'toggle_status':
            $userId = $_POST['user_id'] ?? null;
            if ($userId && is_numeric($userId)) {
                $user = $db->fetch("SELECT username, full_name, is_active FROM users WHERE id = ?", [$userId]);
                if ($user) {
                    $newStatus = $user['is_active'] ? 0 : 1;
                    $db->execute("UPDATE users SET is_active = ? WHERE id = ?", [$newStatus, $userId]);
                    
                    $action_desc = $newStatus ? 'activated' : 'deactivated';
                    logActivity($_SESSION['user_id'], 'user_status_changed', "User {$user['username']} $action_desc");
                    
                    showAlert("User " . ($newStatus ? 'activated' : 'deactivated') . " successfully!", 'success');
                }
            }
            break;
            
        case 'reset_password':
            $userId = $_POST['user_id'] ?? null;
            $newPassword = $_POST['new_password'] ?? '';
            
            if ($userId && is_numeric($userId) && !empty($newPassword)) {
                if (strlen($newPassword) < 6) {
                    showAlert('Password must be at least 6 characters long.', 'danger');
                } else {
                    $user = $db->fetch("SELECT username, full_name FROM users WHERE id = ?", [$userId]);
                    if ($user) {
                        $passwordHash = hashPassword($newPassword);
                        $db->execute("UPDATE users SET password_hash = ? WHERE id = ?", [$passwordHash, $userId]);
                        
                        logActivity($_SESSION['user_id'], 'password_reset', "Password reset for user: {$user['username']}");
                        showAlert('Password reset successfully!', 'success');
                    }
                }
            }
            break;
    }
    
    redirect('users.php');
}

// Get all users
$users = $db->fetchAll("
    SELECT u.*, 
           COUNT(DISTINCT fa.file_id) as accessible_files,
           COUNT(DISTINCT dl.id) as total_downloads,
           MAX(al.created_at) as last_activity
    FROM users u
    LEFT JOIN file_access fa ON u.id = fa.user_id
    LEFT JOIN download_logs dl ON u.id = dl.user_id
    LEFT JOIN activity_logs al ON u.id = al.user_id
    GROUP BY u.id
    ORDER BY u.created_at DESC
");

// Get user statistics
$totalUsers = count($users);
$activeUsers = count(array_filter($users, function($u) { return $u['is_active']; }));
$adminUsers = count(array_filter($users, function($u) { return $u['role'] === 'admin'; }));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - <?php echo APP_NAME; ?></title>
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
                <a class="nav-link active" href="users.php">
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
                    <h1 class="h2">Manage Users</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                        <i class="bi bi-person-plus"></i> Add New User
                    </button>
                </div>

                <?php displayAlert(); ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title">Total Users</h5>
                                        <h2><?php echo $totalUsers; ?></h2>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-people fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title">Active Users</h5>
                                        <h2><?php echo $activeUsers; ?></h2>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-person-check fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title">Administrators</h5>
                                        <h2><?php echo $adminUsers; ?></h2>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-shield-check fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>All Users</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Employee ID</th>
                                        <th>Status</th>
                                        <th>Files Access</th>
                                        <th>Downloads</th>
                                        <th>Last Activity</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($user['username']); ?></small>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($user['role'] === 'admin'): ?>
                                                    <span class="badge bg-danger">Administrator</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">User</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($user['employee_id']): ?>
                                                    <code><?php echo htmlspecialchars($user['employee_id']); ?></code>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($user['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $user['accessible_files']; ?> files</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $user['total_downloads']; ?></span>
                                            </td>
                                            <td>
                                                <?php if ($user['last_activity']): ?>
                                                    <?php echo formatDate($user['last_activity'], 'M j, Y'); ?>
                                                    <br><small class="text-muted"><?php echo formatDate($user['last_activity'], 'g:i A'); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">Never</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($user['id'] != $_SESSION['user_id']): // Prevent self-actions ?>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                onclick="toggleUserStatus(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name'], ENT_QUOTES); ?>', <?php echo $user['is_active'] ? 'true' : 'false'; ?>)"
                                                                title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?> User">
                                                            <i class="bi bi-<?php echo $user['is_active'] ? 'person-x' : 'person-check'; ?>"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-warning" 
                                                                onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name'], ENT_QUOTES); ?>')"
                                                                title="Reset Password">
                                                            <i class="bi bi-key"></i>
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">Current User</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
    </main>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="create_user">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="employee_id" class="form-label">Employee ID</label>
                                    <input type="text" class="form-control" id="employee_id" name="employee_id">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="password" name="password" minlength="6" required>
                                    <div class="form-text">Minimum 6 characters</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role</label>
                                    <select class="form-select" id="role" name="role">
                                        <option value="user">User</option>
                                        <option value="admin">Administrator</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Toggle Status Modal -->
    <div class="modal fade" id="toggleStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="toggleStatusTitle">Confirm Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="toggleStatusMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="user_id" id="toggleUserId">
                        <button type="submit" class="btn btn-primary" id="toggleStatusBtn">Confirm</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="user_id" id="resetUserId">
                        
                        <p>Reset password for: <strong id="resetUserName"></strong></p>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" minlength="6" required>
                            <div class="form-text">Minimum 6 characters</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleUserStatus(userId, userName, isActive) {
            document.getElementById('toggleUserId').value = userId;
            
            if (isActive) {
                document.getElementById('toggleStatusTitle').textContent = 'Deactivate User';
                document.getElementById('toggleStatusMessage').innerHTML = 'Are you sure you want to deactivate <strong>' + userName + '</strong>?<br>They will no longer be able to log in.';
                document.getElementById('toggleStatusBtn').textContent = 'Deactivate';
                document.getElementById('toggleStatusBtn').className = 'btn btn-warning';
            } else {
                document.getElementById('toggleStatusTitle').textContent = 'Activate User';
                document.getElementById('toggleStatusMessage').innerHTML = 'Are you sure you want to activate <strong>' + userName + '</strong>?<br>They will be able to log in again.';
                document.getElementById('toggleStatusBtn').textContent = 'Activate';
                document.getElementById('toggleStatusBtn').className = 'btn btn-success';
            }
            
            const modal = new bootstrap.Modal(document.getElementById('toggleStatusModal'));
            modal.show();
        }
        
        function resetPassword(userId, userName) {
            document.getElementById('resetUserId').value = userId;
            document.getElementById('resetUserName').textContent = userName;
            document.getElementById('new_password').value = '';
            
            const modal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
            modal.show();
        }
    </script>
</body>
</html>
