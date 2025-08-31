<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

requireAdmin();

header('Content-Type: application/json');

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $fileId = $_POST['file_id'] ?? null;
    $userId = $_POST['user_id'] ?? null;

    if (!$fileId || !is_numeric($fileId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid file ID']);
        exit;
    }

    switch ($action) {
        case 'grant':
            if (!$userId || !is_numeric($userId)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
                exit;
            }

            if (grantFileAccess($fileId, $userId, $_SESSION['user_id'])) {
                $user = getUserById($userId);
                $file = $db->fetch("SELECT title FROM payroll_files WHERE id = ?", [$fileId]);
                
                logActivity($_SESSION['user_id'], 'access_granted', 
                    "Granted access to file '{$file['title']}' for user '{$user['full_name']}'");
                
                echo json_encode(['success' => true, 'message' => 'Access granted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to grant access']);
            }
            break;

        case 'revoke':
            if (!$userId || !is_numeric($userId)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
                exit;
            }

            if (revokeFileAccess($fileId, $userId)) {
                $user = getUserById($userId);
                $file = $db->fetch("SELECT title FROM payroll_files WHERE id = ?", [$fileId]);
                
                logActivity($_SESSION['user_id'], 'access_revoked', 
                    "Revoked access to file '{$file['title']}' for user '{$user['full_name']}'");
                
                echo json_encode(['success' => true, 'message' => 'Access revoked successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to revoke access']);
            }
            break;

        case 'grant_all':
            try {
                $db->beginTransaction();
                
                $users = $db->fetchAll("SELECT id FROM users WHERE role = 'user' AND is_active = 1");
                $granted = 0;
                
                foreach ($users as $user) {
                    if (grantFileAccess($fileId, $user['id'], $_SESSION['user_id'])) {
                        $granted++;
                    }
                }
                
                $db->commit();
                
                $file = $db->fetch("SELECT title FROM payroll_files WHERE id = ?", [$fileId]);
                logActivity($_SESSION['user_id'], 'access_granted_all', 
                    "Granted access to file '{$file['title']}' for all users");
                
                echo json_encode(['success' => true, 'message' => "Access granted to $granted users"]);
            } catch (Exception $e) {
                $db->rollback();
                echo json_encode(['success' => false, 'message' => 'Failed to grant access to all users']);
            }
            break;

        case 'revoke_all':
            try {
                $file = $db->fetch("SELECT title FROM payroll_files WHERE id = ?", [$fileId]);
                $count = $db->execute("DELETE FROM file_access WHERE file_id = ?", [$fileId]);
                
                logActivity($_SESSION['user_id'], 'access_revoked_all', 
                    "Revoked all access to file '{$file['title']}'");
                
                echo json_encode(['success' => true, 'message' => 'All access revoked successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to revoke all access']);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
