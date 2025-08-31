<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/database.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect(BASE_URL . 'admin/dashboard.php');
    } else {
        redirect(BASE_URL . 'user/dashboard.php');
    }
}

$db = Database::getInstance();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $username = sanitizeInput($_POST['username'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $fullName = sanitizeInput($_POST['full_name'] ?? '');
        $employeeId = sanitizeInput($_POST['employee_id'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($username) || empty($email) || empty($fullName) || empty($password)) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } elseif (strlen($username) < 3) {
            $error = 'Username must be at least 3 characters long.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $error = 'Username can only contain letters, numbers, and underscores.';
        } else {
            // Check if username or email already exists
            $existingUser = $db->fetch(
                "SELECT id FROM users WHERE username = ? OR email = ?", 
                [$username, $email]
            );
            
            if ($existingUser) {
                $error = 'Username or email address already exists. Please choose different ones.';
            } else {
                try {
                    $db->beginTransaction();
                    
                    // Create the user account
                    $passwordHash = hashPassword($password);
                    $sql = "INSERT INTO users (username, email, password_hash, full_name, employee_id, role, is_active) 
                            VALUES (?, ?, ?, ?, ?, 'user', 1)";
                    
                    $db->execute($sql, [
                        $username,
                        $email,
                        $passwordHash,
                        $fullName,
                        $employeeId ?: null
                    ]);
                    
                    $userId = $db->lastInsertId();
                    
                    $db->commit();
                    
                    // Log the registration activity
                    logActivity($userId, 'user_registered', "New user registered: $username ($fullName)");
                    
                    // Log admin notification about new user
                    logActivity(null, 'new_user_registered', "New user registration requires admin review: $username ($fullName, $email)");
                    
                    $success = 'Account created successfully! You can now log in with your credentials.';
                    
                    // Clear form data on success
                    $username = $email = $fullName = $employeeId = '';
                    
                } catch (Exception $e) {
                    $db->rollback();
                    $error = 'Failed to create account. Please try again.';
                    error_log("User registration error: " . $e->getMessage());
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card mt-4 mb-4">
                    <div class="card-header text-center">
                        <h4><?php echo APP_NAME; ?></h4>
                        <p class="mb-0">Create Your Account</p>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-circle"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle"></i>
                                <?php echo htmlspecialchars($success); ?>
                                <div class="mt-2">
                                    <a href="login.php" class="btn btn-sm btn-success">
                                        <i class="bi bi-box-arrow-in-right"></i> Go to Login
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">
                                            Username <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?php echo htmlspecialchars($username ?? ''); ?>" 
                                               minlength="3" maxlength="50" required>
                                        <div class="form-text">3-50 characters, letters, numbers, and underscores only</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">
                                            Email Address <span class="text-danger">*</span>
                                        </label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($email ?? ''); ?>" 
                                               maxlength="100" required>
                                        <div class="form-text">Your active email address</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label">
                                            Full Name <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" 
                                               value="<?php echo htmlspecialchars($fullName ?? ''); ?>" 
                                               maxlength="100" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="employee_id" class="form-label">Employee ID</label>
                                        <input type="text" class="form-control" id="employee_id" name="employee_id" 
                                               value="<?php echo htmlspecialchars($employeeId ?? ''); ?>" 
                                               maxlength="50">
                                        <div class="form-text">Optional</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="password" class="form-label">
                                            Password <span class="text-danger">*</span>
                                        </label>
                                        <input type="password" class="form-control" id="password" name="password" 
                                               minlength="6" required>
                                        <div class="form-text">Minimum 6 characters</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">
                                            Confirm Password <span class="text-danger">*</span>
                                        </label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                               minlength="6" required>
                                        <div class="form-text">Re-enter your password</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <strong>Account Information:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Your account will be created as a standard user account</li>
                                    <li>An administrator will need to grant you access to specific payroll files</li>
                                    <li>You will receive access to payroll documents assigned to you</li>
                                </ul>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-person-plus"></i> Create Account
                                </button>
                            </div>
                        </form>
                        
                        <hr>
                        <div class="text-center">
                            <p class="mb-0">Already have an account?</p>
                            <a href="login.php" class="btn btn-link">
                                <i class="bi bi-box-arrow-in-right"></i> Sign In
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword && confirmPassword.length > 0) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Username validation
        document.getElementById('username').addEventListener('input', function() {
            const username = this.value;
            const regex = /^[a-zA-Z0-9_]+$/;
            
            if (!regex.test(username) && username.length > 0) {
                this.setCustomValidity('Username can only contain letters, numbers, and underscores');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
