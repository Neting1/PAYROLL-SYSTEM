<?php
require_once 'database.php';

/**
 * Generate a secure token for password reset
 */
function generateResetToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Create a password reset request
 */
function createPasswordResetToken($userId) {
    $db = Database::getInstance();
    
    // Delete any existing tokens for this user
    $db->execute("DELETE FROM password_resets WHERE user_id = ?", [$userId]);
    
    // Generate token and set expiration
    $token = generateResetToken();
    $expires = date('Y-m-d H:i:s', time() + PASSWORD_RESET_EXPIRY);
    
    // Insert new token
    $db->execute("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)", 
                 [$userId, $token, $expires]);
    
    return $token;
}

/**
 * Validate a password reset token
 */
function validateResetToken($token) {
    $db = Database::getInstance();
    
    $sql = "
        SELECT pr.*, u.email, u.username 
        FROM password_resets pr 
        JOIN users u ON pr.user_id = u.id 
        WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
    ";
    
    return $db->fetch($sql, [$token]);
}

/**
 * Mark a token as used
 */
function markTokenAsUsed($tokenId) {
    $db = Database::getInstance();
    return $db->execute("UPDATE password_resets SET used = 1 WHERE id = ?", [$tokenId]);
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($email, $token) {
    $resetLink = BASE_URL . "reset_password.php?token=" . urlencode($token);
    
    $subject = "Password Reset Request - " . APP_NAME;
    $message = "
        <html>
        <head>
            <title>Password Reset Request</title>
        </head>
        <body>
            <h2>Password Reset Request</h2>
            <p>You have requested to reset your password for your " . APP_NAME . " account.</p>
            <p>Please click the link below to reset your password:</p>
            <p><a href='$resetLink'>Reset Password</a></p>
            <p>This link will expire in 1 hour.</p>
            <p>If you did not request this password reset, please ignore this email.</p>
        </body>
        </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM . ">" . "\r\n";
    
    // For testing on localhost, display the link instead of emailing
    if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
        echo "<div class='alert alert-info'>For testing: <a href='$resetLink'>Reset Link</a></div>";
        return true;
    }
    
    return mail($email, $subject, $message, $headers);
}

/**
 * Get user by email
 */
function getUserByEmail($email) {
    $db = Database::getInstance();
    return $db->fetch("SELECT * FROM users WHERE email = ?", [$email]);
}

/**
 * Update user password
 */
function updateUserPassword($userId, $password) {
    $db = Database::getInstance();
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    return $db->execute("UPDATE users SET password_hash = ? WHERE id = ?", [$hashedPassword, $userId]);
}
?>