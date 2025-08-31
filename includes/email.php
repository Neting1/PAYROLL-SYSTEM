<?php
// /includes/email.php
// Email functionality for Twinhill Payroll System

// Load configuration
require_once __DIR__ . '/config.php';

/**
 * Send email using PHPMailer or system mail function
 */
function sendEmail($to, $subject, $body, $from = '', $fromName = '', $attachments = []) {
    if (empty($from)) {
        $from = SYSTEM_EMAIL;
    }
    
    if (empty($fromName)) {
        $fromName = SYSTEM_NAME;
    }
    
    // Try to use PHPMailer if available
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        return sendEmailWithPHPMailer($to, $subject, $body, $from, $fromName, $attachments);
    } else {
        return sendEmailWithMailFunction($to, $subject, $body, $from, $fromName);
    }
}

/**
 * Send email using PHPMailer library
 */
function sendEmailWithPHPMailer($to, $subject, $body, $from, $fromName, $attachments) {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = SMTP_AUTH;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        
        // Recipients
        $mail->setFrom($from, $fromName);
        $mail->addAddress($to);
        
        // Attachments
        foreach ($attachments as $attachment) {
            $mail->addAttachment($attachment);
        }
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send email using PHP's built-in mail function
 */
function sendEmailWithMailFunction($to, $subject, $body, $from, $fromName) {
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: "' . $fromName . '" <' . $from . '>',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    return mail($to, $subject, $body, implode("\r\n", $headers));
}

/**
 * Send bulk email notifications for file access
 */
function sendBulkFileAccessNotifications($users, $fileInfo, $adminName) {
    $results = [];
    
    foreach ($users as $user) {
        $success = sendFileAccessNotification(
            $user['email'], 
            $user['full_name'], 
            $fileInfo, 
            $adminName
        );
        
        $results[] = [
            'user_id' => $user['id'],
            'email' => $user['email'],
            'success' => $success
        ];
    }
    
    return $results;
}

/**
 * Send email notification for file access
 */
function sendFileAccessNotification($email, $userName, $fileInfo, $adminName) {
    $subject = "New Payroll File Available: " . $fileInfo['title'];
    
    $portalLink = getBaseUrl() . "/index.php";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>New Payroll File Available</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4361ee; color: white; padding: 15px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background-color: #f8f9fa; padding: 20px; border: 1px solid #ddd; border-top: none; }
            .file-details { background-color: #e9ecef; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .button { display: inline-block; background-color: #4361ee; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; }
            .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #6c757d; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>" . SYSTEM_NAME . "</h2>
            </div>
            <div class='content'>
                <p>Hello <strong>" . htmlspecialchars($userName) . "</strong>,</p>
                <p>You have been granted access to a new payroll file by <strong>" . htmlspecialchars($adminName) . "</strong>.</p>
                
                <div class='file-details'>
                    <h3>File Details:</h3>
                    <p><strong>Title:</strong> " . htmlspecialchars($fileInfo['title']) . "</p>";
    
    if (!empty($fileInfo['pay_period'])) {
        $message .= "<p><strong>Pay Period:</strong> " . htmlspecialchars($fileInfo['pay_period']) . "</p>";
    }
    
    if (!empty($fileInfo['description'])) {
        $message .= "<p><strong>Description:</strong> " . htmlspecialchars($fileInfo['description']) . "</p>";
    }
    
    $message .= "
                </div>
                
                <p>Please log in to the portal to access this file:</p>
                <p style='text-align: center;'>
                    <a href='" . $portalLink . "' class='button'>Access Portal</a>
                </p>
                
                <p>Thank you,<br>The " . SYSTEM_NAME . " Team</p>
            </div>
            <div class='footer'>
                <p>This is an automated message. Please do not reply to this email.</p>
                <p>If you have any questions, please contact your system administrator.</p>
            </div>
        </div>
    </body>
    </html>";
    
    return sendEmail($email, $subject, $message);
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($email, $token, $username) {
    $resetLink = getBaseUrl() . "/reset_password.php?token=" . urlencode($token);
    
    $subject = "Password Reset Request - " . SYSTEM_NAME;
    
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Password Reset</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4361ee; color: white; padding: 15px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background-color: #f8f9fa; padding: 20px; border: 1px solid #ddd; border-top: none; }
            .button { display: inline-block; background-color: #4361ee; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; }
            .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #6c757d; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>" . SYSTEM_NAME . "</h2>
            </div>
            <div class='content'>
                <p>Hello " . htmlspecialchars($username) . ",</p>
                <p>We received a request to reset your password for your " . SYSTEM_NAME . " account.</p>
                <p>Please click the button below to reset your password:</p>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='" . $resetLink . "' class='button'>Reset Password</a>
                </p>
                <p>If you did not request a password reset, please ignore this email. The reset link will expire in 1 hour.</p>
                <p>Thank you,<br>The " . SYSTEM_NAME . " Team</p>
            </div>
            <div class='footer'>
                <p>If you're having trouble clicking the button, copy and paste the URL below into your web browser:</p>
                <p>" . $resetLink . "</p>
            </div>
        </div>
    </body>
    </html>";
    
    return sendEmail($email, $subject, $body);
}

/**
 * Send account activation email
 */
function sendAccountActivationEmail($email, $token, $username) {
    $activationLink = getBaseUrl() . "/activate_account.php?token=" . urlencode($token);
    
    $subject = "Activate Your Account - " . SYSTEM_NAME;
    
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Account Activation</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4361ee; color: white; padding: 15px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background-color: #f8f9fa; padding: 20px; border: 1px solid #ddd; border-top: none; }
            .button { display: inline-block; background-color: #4361ee; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; }
            .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #6c757d; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Welcome to " . SYSTEM_NAME . "</h2>
            </div>
            <div class='content'>
                <p>Hello " . htmlspecialchars($username) . ",</p>
                <p>Thank you for registering with " . SYSTEM_NAME . ". To complete your registration, please activate your account by clicking the button below:</p>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='" . $activationLink . "' class='button'>Activate Account</a>
                </p>
                <p>If you did not create an account, please ignore this email.</p>
                <p>Thank you,<br>The " . SYSTEM_NAME . " Team</p>
            </div>
            <div class='footer'>
                <p>If you're having trouble clicking the button, copy and paste the URL below into your web browser:</p>
                <p>" . $activationLink . "</p>
            </div>
        </div>
    </body>
    </html>";
    
    return sendEmail($email, $subject, $body);
}

/**
 * Send notification email to administrators
 */
function sendAdminNotification($subject, $message) {
    $adminEmails = getAdminEmails();
    
    if (empty($adminEmails)) {
        error_log("No admin emails configured for notifications");
        return false;
    }
    
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Admin Notification</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4361ee; color: white; padding: 15px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background-color: #f8f9fa; padding: 20px; border: 1px solid #ddd; border-top: none; }
            .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #6c757d; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>" . SYSTEM_NAME . " Notification</h2>
            </div>
            <div class='content'>
                <p>" . nl2br(htmlspecialchars($message)) . "</p>
                <p>This is an automated notification from the " . SYSTEM_NAME . ".</p>
            </div>
            <div class='footer'>
                <p>Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>";
    
    $success = true;
    foreach ($adminEmails as $email) {
        if (!sendEmail($email, $subject, $body)) {
            $success = false;
            error_log("Failed to send admin notification to: " . $email);
        }
    }
    
    return $success;
}

/**
 * Log email activity to database
 */
function logEmailActivity($userId, $type, $recipient, $success) {
    global $db;
    
    try {
        return $db->insert('email_logs', [
            'user_id' => $userId,
            'email_type' => $type,
            'recipient_email' => $recipient,
            'sent_status' => $success ? 'success' : 'failed',
            'sent_at' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        error_log("Failed to log email activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Get admin email addresses from database
 */
function getAdminEmails() {
    global $db;
    
    try {
        $admins = $db->fetchAll("SELECT email FROM users WHERE role = 'admin' AND is_active = 1");
        return array_column($admins, 'email');
    } catch (Exception $e) {
        error_log("Failed to fetch admin emails: " . $e->getMessage());
        return [ADMIN_EMAIL]; // Fallback to config email
    }
}

/**
 * Get base URL for the application
 */
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    
    // Remove any trailing slashes
    $path = rtrim($path, '/');
    
    return $protocol . $host . $path;
}

// Check if this file is being accessed directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    header('HTTP/1.0 403 Forbidden');
    die('Access denied.');
}