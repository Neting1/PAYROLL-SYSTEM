<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

requireLogin();

$db = Database::getInstance();

// Get file ID
$fileId = $_GET['id'] ?? null;

if (!$fileId || !is_numeric($fileId)) {
    showAlert('Invalid file ID.', 'error');
    redirect(BASE_URL . 'user/dashboard.php');
}

// Get file information
$sql = "SELECT * FROM payroll_files WHERE id = ? AND is_active = 1";
$file = $db->fetch($sql, [$fileId]);

if (!$file) {
    showAlert('File not found.', 'error');
    redirect(BASE_URL . 'user/dashboard.php');
}

// Check if user has access to this file
if (!hasFileAccess($fileId, $_SESSION['user_id'])) {
    showAlert('You do not have permission to access this file.', 'error');
    redirect(BASE_URL . 'user/dashboard.php');
}

// Check if file exists on disk
if (!file_exists($file['file_path'])) {
    showAlert('File not found on server. Please contact administrator.', 'error');
    redirect(BASE_URL . 'user/dashboard.php');
}

// Log the view activity
logActivity($_SESSION['user_id'], 'file_view', "Viewed file: " . $file['title']);

// Set headers for file viewing
header('Content-Type: ' . $file['mime_type']);
header('Content-Disposition: inline; filename="' . $file['original_filename'] . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Content-Length: ' . $file['file_size']);

// Clear any output buffer
if (ob_get_level()) {
    ob_end_clean();
}

// Read and output the file
readfile($file['file_path']);
exit();
?>
