<?php
require_once 'config.php';
require_once 'database.php';

// Security functions
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

function isAdmin() {
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . 'user/dashboard.php');
        exit();
    }
}

// User management functions
function getUserById($userId) {
    $db = Database::getInstance();
    $sql = "SELECT id, username, email, full_name, employee_id, role, is_active, created_at 
            FROM users WHERE id = ? AND is_active = 1";
    return $db->fetch($sql, [$userId]);
}

function getUserByUsername($username) {
    $db = Database::getInstance();
    $sql = "SELECT * FROM users WHERE username = ? AND is_active = 1";
    return $db->fetch($sql, [$username]);
}

// File handling functions
function generateUniqueFilename($originalFilename) {
    $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
    return uniqid('payroll_', true) . '.' . strtolower($extension);
}

function isValidFileType($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, ALLOWED_EXTENSIONS);
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Logging functions
function logActivity($userId, $action, $description = '', $ipAddress = null, $userAgent = null) {
    $db = Database::getInstance();
    
    if (!$ipAddress) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    if (!$userAgent) {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    $sql = "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)";
    
    try {
        $db->execute($sql, [$userId, $action, $description, $ipAddress, $userAgent]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

function logDownload($fileId, $userId, $ipAddress = null, $userAgent = null) {
    $db = Database::getInstance();
    
    if (!$ipAddress) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    if (!$userAgent) {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    $sql = "INSERT INTO download_logs (file_id, user_id, ip_address, user_agent) 
            VALUES (?, ?, ?, ?)";
    
    try {
        $db->execute($sql, [$fileId, $userId, $ipAddress, $userAgent]);
        
        // Update download count
        $updateSql = "UPDATE payroll_files SET download_count = download_count + 1 WHERE id = ?";
        $db->execute($updateSql, [$fileId]);
    } catch (Exception $e) {
        error_log("Failed to log download: " . $e->getMessage());
    }
}

// File access functions
function hasFileAccess($fileId, $userId) {
    $db = Database::getInstance();
    
    // Admin has access to all files
    $user = getUserById($userId);
    if ($user && $user['role'] === 'admin') {
        return true;
    }
    
    // Check if user has specific access to this file
    $sql = "SELECT 1 FROM file_access WHERE file_id = ? AND user_id = ?";
    $result = $db->fetch($sql, [$fileId, $userId]);
    
    return $result !== false;
}

function grantFileAccess($fileId, $userId, $grantedBy) {
    $db = Database::getInstance();
    $sql = "INSERT IGNORE INTO file_access (file_id, user_id, granted_by) VALUES (?, ?, ?)";
    return $db->execute($sql, [$fileId, $userId, $grantedBy]);
}

function revokeFileAccess($fileId, $userId) {
    $db = Database::getInstance();
    $sql = "DELETE FROM file_access WHERE file_id = ? AND user_id = ?";
    return $db->execute($sql, [$fileId, $userId]);
}

// Template functions
function redirect($url, $permanent = false) {
    if ($permanent) {
        header('HTTP/1.1 301 Moved Permanently');
    }
    header('Location: ' . $url);
    exit();
}

function showAlert($message, $type = 'info') {
    $_SESSION['alert'] = [
        'message' => $message,
        'type' => $type
    ];
}

function displayAlert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        echo '<div class="alert alert-' . $alert['type'] . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($alert['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
        unset($_SESSION['alert']);
    }
}

function formatDate($date, $format = 'Y-m-d H:i:s') {
    return date($format, strtotime($date));
}
?>