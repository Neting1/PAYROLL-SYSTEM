<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/database.php';
require_once 'includes/password_reset_functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect(BASE_URL . 'admin/dashboard.php');
    } else {
        redirect(BASE_URL . 'user/dashboard.php');
    }
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = sanitizeInput($_POST['email'] ?? '');
        
        if (empty($email)) {
            $error = 'Please enter your email address.';
        } else {
            $user = getUserByEmail($email);
            
            if ($user) {
                // Create reset token
                $token = createPasswordResetToken($user['id']);
                
                // Send email
                if (sendPasswordResetEmail($user['email'], $token)) {
                    $message = 'Password reset instructions have been sent to your email.';
                } else {
                    $error = 'Failed to send email. Please try again later.';
                }
            } else {
                // For security, don't reveal if email exists or not
                $message = 'If your email exists in our system, you will receive password reset instructions.';
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
    <title>Forgot Password - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Reuse the same styles from login page */
        :root {
            --primary: #0d6efd;
            --primary-dark: #0a58ca;
            --secondary: #6c757d;
            --light: #f8f9fa;
            --dark: #212529;
            --success: #198754;
            --danger: #dc3545;
            --warning: #ffc107;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 20px;
        }
        
        .login-container {
            max-width: 400px;
            width: 100%;
            margin: 0 auto;
            padding: 20px; /* Add padding to container */
        }
        
        .login-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.175);
            overflow: hidden;
            background: white;
            width: 100%; /* Ensure card takes full width of container */
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .login-header h2 {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-control {
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            border: 1px solid #dee2e6;
            transition: all 0.2s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        .input-group-text {
            background: white;
            border-radius: 0.5rem 0 0 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            border-radius: 0.5rem;
            padding: 0.75rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .alert {
            border-radius: 0.5rem;
            border: none;
            padding: 0.75rem 1rem;
        }
        
        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fadeIn {
            animation: fadeIn 0.6s ease-out;
        }
        
        /* Responsive adjustments */
        @media (max-width: 576px) {
            .login-body {
                padding: 1.5rem;
            }
            
            .login-header {
                padding: 1.5rem;
            }
        }
        
        /* Fix for the text alignment */
        .text-center {
            margin-top: 1rem;
            margin-bottom: 1rem;
        }
        
        /* Add hover effect for the back link */
        .text-center a:hover {
            color: var(--primary-dark);
            text-decoration: underline !important;
        }
    </style>
</head>
<body>
    <div class="login-container animate-fadeIn">
        <div class="login-card">
            <div class="login-header">
                <h2><i class="bi bi-wallet2 me-2"></i><?php echo APP_NAME; ?></h2>
                <p class="mb-0">Reset Your Password</p>
            </div>
            
            <div class="login-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($message)): ?>
                    <div class="alert alert-info d-flex align-items-center" role="alert">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <div><?php echo htmlspecialchars($message); ?></div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-4">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="Enter your email address" required>
                            <div class="invalid-feedback">
                                Please enter a valid email address.
                            </div>
                        </div>
                        <div class="form-text">
                            Enter your email address and we'll send you instructions to reset your password.
                        </div>
                    </div>
                    
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-2"></i> Send Reset Instructions
                        </button>
                    </div>
                </form>
                
                <div class="text-center">
                    <a href="login.php" class="text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i> Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        
                        form.classList.add('was-validated')
                    }, false)
                })
        })()
    </script>
</body>
</html>