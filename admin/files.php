<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

requireAdmin();

$db = Database::getInstance();

// Handle file deletion
if ($_POST['action'] ?? '' === 'delete' && isset($_POST['file_id'])) {
    if (validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $fileId = $_POST['file_id'];
        
        // Get file info before deletion
        $file = $db->fetch("SELECT * FROM payroll_files WHERE id = ?", [$fileId]);
        
        if ($file) {
            try {
                $db->beginTransaction();
                
                // Soft delete the file record
                $db->execute("UPDATE payroll_files SET is_active = 0 WHERE id = ?", [$fileId]);
                
                // Remove file access records
                $db->execute("DELETE FROM file_access WHERE file_id = ?", [$fileId]);
                
                $db->commit();
                
                // Log the activity
                logActivity($_SESSION['user_id'], 'file_delete', "Deleted file: " . $file['title']);
                
                showAlert('File deleted successfully.', 'success');
            } catch (Exception $e) {
                $db->rollback();
                showAlert('Failed to delete file.', 'danger');
                error_log("File deletion error: " . $e->getMessage());
            }
        }
    }
    redirect('files.php');
}

// Get all files
$files = $db->fetchAll("
    SELECT pf.*, u.full_name as uploaded_by_name,
           COUNT(fa.user_id) as access_count,
           GROUP_CONCAT(DISTINCT u2.full_name SEPARATOR ', ') as access_users
    FROM payroll_files pf 
    LEFT JOIN users u ON pf.uploaded_by = u.id
    LEFT JOIN file_access fa ON pf.id = fa.file_id
    LEFT JOIN users u2 ON fa.user_id = u2.id AND u2.is_active = 1
    WHERE pf.is_active = 1 
    GROUP BY pf.id
    ORDER BY pf.upload_date DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Files - <?php echo APP_NAME; ?></title>
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
                    <h1 class="h2">Manage Files</h1>
                    <a href="upload.php" class="btn btn-primary">
                        <i class="bi bi-cloud-upload"></i> Upload New File
                    </a>
                </div>

                <?php displayAlert(); ?>

                <div class="card">
                    <div class="card-body">
                        <?php if (empty($files)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                <h4 class="text-muted mt-3">No files uploaded</h4>
                                <p class="text-muted">Upload your first payroll file to get started.</p>
                                <a href="upload.php" class="btn btn-primary">
                                    <i class="bi bi-cloud-upload"></i> Upload File
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Pay Period</th>
                                            <th>Uploaded</th>
                                            <th>Size</th>
                                            <th>Downloads</th>
                                            <th>User Access</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($files as $file): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($file['title']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($file['original_filename']); ?></small>
                                                        <?php if ($file['description']): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($file['description']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($file['pay_period']): ?>
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($file['pay_period']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo formatDate($file['upload_date'], 'M j, Y'); ?>
                                                    <br><small class="text-muted">by <?php echo htmlspecialchars($file['uploaded_by_name'] ?? 'Unknown'); ?></small>
                                                </td>
                                                <td><?php echo formatFileSize($file['file_size']); ?></td>
                                                <td>
                                                    <span class="badge bg-secondary"><?php echo $file['download_count']; ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($file['access_count'] > 0): ?>
                                                        <span class="badge bg-success"><?php echo $file['access_count']; ?> users</span>
                                                        <br><small class="text-muted" title="<?php echo htmlspecialchars($file['access_users']); ?>">
                                                            <?php echo strlen($file['access_users']) > 30 ? substr($file['access_users'], 0, 30) . '...' : $file['access_users']; ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">No access granted</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="manage_access.php?id=<?php echo $file['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary" title="Manage Access">
                                                            <i class="bi bi-people"></i>
                                                        </a>
                                                        <a href="../user/view.php?id=<?php echo $file['id']; ?>" 
                                                           class="btn btn-sm btn-outline-secondary" 
                                                           target="_blank" title="View">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <a href="../user/download.php?id=<?php echo $file['id']; ?>" 
                                                           class="btn btn-sm btn-outline-info" title="Download">
                                                            <i class="bi bi-download"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                onclick="confirmDelete(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['title'], ENT_QUOTES); ?>')"
                                                                title="Delete">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
    </main>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the file "<span id="deleteFileName"></span>"?</p>
                    <p class="text-danger"><strong>Warning:</strong> This action cannot be undone. The file will be removed from the system and users will no longer be able to access it.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="file_id" id="deleteFileId">
                        <button type="submit" class="btn btn-danger">Delete File</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(fileId, fileName) {
            document.getElementById('deleteFileId').value = fileId;
            document.getElementById('deleteFileName').textContent = fileName;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
    </script>
</body>
</html>
