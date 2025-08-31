<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

requireAdmin();

$db = Database::getInstance();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $title = sanitizeInput($_POST['title'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $payPeriod = sanitizeInput($_POST['pay_period'] ?? '');
        $selectedUsers = $_POST['selected_users'] ?? [];
        
        if (empty($title)) {
            $error = 'Please enter a title for the payroll files.';
        } elseif (empty($_FILES['payroll_files']['name'][0])) {
            $error = 'Please select at least one PDF file to upload.';
        } else {
            $files = $_FILES['payroll_files'];
            $fileCount = count($files['name']);
            
            // Validate all files
            $validFiles = [];
            for ($i = 0; $i < $fileCount; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    $error = "File upload failed for " . $files['name'][$i] . ". Please try again.";
                    break;
                } elseif ($files['size'][$i] > MAX_FILE_SIZE) {
                    $error = "File size exceeds the maximum allowed size for " . $files['name'][$i] . " (" . formatFileSize(MAX_FILE_SIZE) . ").";
                    break;
                } elseif (!isValidFileType($files['name'][$i])) {
                    $error = "Only PDF files are allowed. Invalid file: " . $files['name'][$i];
                    break;
                } else {
                    $validFiles[] = [
                        'name' => $files['name'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'size' => $files['size'][$i]
                    ];
                }
            }
            
            if (empty($error)) {
                try {
                    $db->beginTransaction();
                    $uploadedFiles = [];
                    $failedUploads = [];
                    
                    // Process each file
                    foreach ($validFiles as $file) {
                        // Generate unique filename
                        $filename = generateUniqueFilename($file['name']);
                        $uploadPath = UPLOAD_DIR . $filename;
                        
                        // Create upload directory if it doesn't exist
                        if (!is_dir(UPLOAD_DIR)) {
                            mkdir(UPLOAD_DIR, 0755, true);
                        }
                        
                        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                            // Insert file record
                            $sql = "INSERT INTO payroll_files (filename, original_filename, file_path, file_size, title, description, pay_period, uploaded_by) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                            
                            $db->execute($sql, [
                                $filename,
                                $file['name'],
                                $uploadPath,
                                $file['size'],
                                $title,
                                $description,
                                $payPeriod,
                                $_SESSION['user_id']
                            ]);
                            
                            $fileId = $db->lastInsertId();
                            $uploadedFiles[] = $fileId;
                            
                            // Grant access to selected users
                            if (!empty($selectedUsers)) {
                                foreach ($selectedUsers as $userId) {
                                    grantFileAccess($fileId, $userId, $_SESSION['user_id']);
                                }
                            }
                        } else {
                            $failedUploads[] = $file['name'];
                        }
                    }
                    
                    if (!empty($failedUploads)) {
                        $error = "Failed to upload some files: " . implode(", ", $failedUploads);
                        $db->rollback();
                        
                        // Remove any successfully uploaded files
                        foreach ($uploadedFiles as $fileId) {
                            $filePath = $db->fetchColumn("SELECT file_path FROM payroll_files WHERE id = ?", [$fileId]);
                            if ($filePath && file_exists($filePath)) {
                                unlink($filePath);
                            }
                        }
                    } else {
                        $db->commit();
                        
                        // Log the activity
                        logActivity($_SESSION['user_id'], 'file_upload', "Uploaded $fileCount payroll files: $title");
                        
                        // Send email notifications to users who were granted access
                        if (!empty($selectedUsers)) {
                            // Get user details for email notifications
                            $userIds = implode(',', array_map('intval', $selectedUsers));
                            $usersToNotify = $db->fetchAll(
                                "SELECT id, email, full_name FROM users WHERE id IN ($userIds) AND is_active = 1"
                            );
                            
                            if (!empty($usersToNotify)) {
                                $fileInfo = [
                                    'title' => $title,
                                    'description' => $description,
                                    'pay_period' => $payPeriod
                                ];
                                
                                $adminName = $_SESSION['full_name'];
                                
                                // Log email activities
                                $emailSuccessCount = 0;
                                $emailFailureCount = 0;
                                
                               
                                
                                // Update success message with email status
                                if ($emailSuccessCount > 0 && $emailFailureCount == 0) {
                                    $success = "$fileCount payroll files uploaded successfully! Email notifications sent to $emailSuccessCount user(s).";
                                } elseif ($emailSuccessCount > 0 && $emailFailureCount > 0) {
                                    $success = "$fileCount payroll files uploaded successfully! Email notifications sent to $emailSuccessCount user(s). $emailFailureCount email(s) failed to send.";
                                } else {
                                    $success = "$fileCount payroll files uploaded successfully! However, email notifications could not be sent to users.";
                                }
                            } else {
                                $success = "$fileCount payroll files uploaded successfully!";
                            }
                        } else {
                            $success = "$fileCount payroll files uploaded successfully!";
                        }
                    }
                } catch (Exception $e) {
                    $db->rollback();
                    $error = 'Failed to save file information to database.';
                    error_log("File upload error: " . $e->getMessage());
                    
                    // Remove any uploaded files
                    foreach ($validFiles as $file) {
                        $filename = generateUniqueFilename($file['name']);
                        $uploadPath = UPLOAD_DIR . $filename;
                        if (file_exists($uploadPath)) {
                            unlink($uploadPath);
                        }
                    }
                }
            }
        }
    }
}

// Get all users for access control
$users = $db->fetchAll("SELECT id, username, full_name, employee_id FROM users WHERE role = 'user' AND is_active = 1 ORDER BY full_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Files - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <style>
        .file-input-container {
            position: relative;
        }
        .file-input-container .btn-remove {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            display: none;
        }
        .file-input-container:hover .btn-remove {
            display: block;
        }
        #file-list {
            max-height: 200px;
            overflow-y: auto;
        }
    </style>
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
                <a class="nav-link active" href="upload.php">
                    <i class="bi bi-cloud-upload"></i> Upload Files
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="files.php">
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
            <h1 class="h2">Upload Payroll Files</h1>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo htmlspecialchars($title ?? ''); ?>" required>
                                <div class="form-text">Enter a descriptive title for all payroll files</div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                                <div class="form-text">Optional description or notes about these payroll files</div>
                            </div>

                            <div class="mb-3">
                                <label for="pay_period" class="form-label">Pay Period</label>
                                <input type="text" class="form-control" id="pay_period" name="pay_period" 
                                       value="<?php echo htmlspecialchars($payPeriod ?? ''); ?>" 
                                       placeholder="e.g., January 2024, Q1 2024, etc.">
                                <div class="form-text">The pay period these files cover</div>
                            </div>

                            <div class="mb-3">
                                <label for="payroll_files" class="form-label">PDF Files <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" id="payroll_files" name="payroll_files[]" 
                                       accept=".pdf" multiple required>
                                <div class="form-text">
                                    Select multiple PDF files. Maximum file size: <?php echo formatFileSize(MAX_FILE_SIZE); ?> per file
                                </div>
                                
                                <div id="file-list" class="mt-2 p-2 border rounded">
                                    <p class="text-muted mb-0">No files selected</p>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Grant Access To Users</label>
                                <div class="form-text mb-2">Select which users should have access to these files (leave empty to grant access to all users later)</div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <button type="button" class="btn btn-sm btn-outline-primary" id="selectAll">Select All</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="selectNone">Select None</button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <?php foreach ($users as $user): ?>
                                        <div class="col-md-6 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input user-checkbox" type="checkbox" 
                                                       name="selected_users[]" value="<?php echo $user['id']; ?>" 
                                                       id="user_<?php echo $user['id']; ?>">
                                                <label class="form-check-label" for="user_<?php echo $user['id']; ?>">
                                                    <?php echo htmlspecialchars($user['full_name']); ?>
                                                    <?php if ($user['employee_id']): ?>
                                                        <small class="text-muted">(<?php echo htmlspecialchars($user['employee_id']); ?>)</small>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-cloud-upload"></i> Upload Files
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Upload Guidelines</h5>
                        <ul class="list-unstyled">
                            <li><i class="bi bi-check-circle text-success"></i> Only PDF files are accepted</li>
                            <li><i class="bi bi-check-circle text-success"></i> Maximum file size: <?php echo formatFileSize(MAX_FILE_SIZE); ?> per file</li>
                            <li><i class="bi bi-check-circle text-success"></i> Use descriptive titles</li>
                            <li><i class="bi bi-check-circle text-success"></i> Select appropriate users for access</li>
                            <li><i class="bi bi-check-circle text-success"></i> You can upload multiple files at once</li>
                        </ul>
                        
                        <hr>
                        
                        <h6>File Access Control</h6>
                        <p class="small text-muted">
                            You can grant access to specific users during upload, or manage access later 
                            from the file management page. Users will only see files they have access to.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('selectAll').addEventListener('click', function() {
            document.querySelectorAll('.user-checkbox').forEach(checkbox => {
                checkbox.checked = true;
            });
        });
        
        document.getElementById('selectNone').addEventListener('click', function() {
            document.querySelectorAll('.user-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
        });
        
        // Handle file selection display
        document.getElementById('payroll_files').addEventListener('change', function(e) {
            const fileList = document.getElementById('file-list');
            const files = e.target.files;
            
            if (files.length === 0) {
                fileList.innerHTML = '<p class="text-muted mb-0">No files selected</p>';
                return;
            }
            
            let html = '<ul class="list-group list-group-flush">';
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const size = (file.size / 1024 / 1024).toFixed(2); // Convert to MB
                html += `
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                        <div>
                            <i class="bi bi-file-earmark-pdf text-danger me-2"></i>
                            <span>${file.name}</span>
                        </div>
                        <small class="text-muted">${size} MB</small>
                    </li>
                `;
            }
            html += '</ul>';
            
            fileList.innerHTML = html;
        });
        
        // Form validation
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const files = document.getElementById('payroll_files').files;
            if (files.length === 0) {
                e.preventDefault();
                alert('Please select at least one PDF file to upload.');
                return;
            }
            
            // Validate each file
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const maxSize = <?php echo MAX_FILE_SIZE; ?>;
                
                if (file.size > maxSize) {
                    e.preventDefault();
                    alert(`File "${file.name}" exceeds the maximum allowed size of <?php echo formatFileSize(MAX_FILE_SIZE); ?>.`);
                    return;
                }
                
                if (!file.name.toLowerCase().endsWith('.pdf')) {
                    e.preventDefault();
                    alert(`Only PDF files are allowed. File "${file.name}" is not a PDF.`);
                    return;
                }
            }
        });
    </script>
</body>
</html>