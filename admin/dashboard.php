<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

requireAdmin();

$db = Database::getInstance();

// Get statistics
$totalFiles = $db->fetch("SELECT COUNT(*) as count FROM payroll_files WHERE is_active = 1")['count'];
$totalUsers = $db->fetch("SELECT COUNT(*) as count FROM users WHERE is_active = 1 AND role = 'user'")['count'];
$totalDownloads = $db->fetch("SELECT SUM(download_count) as total FROM payroll_files WHERE is_active = 1")['total'] ?? 0;

// Get recent files
$recentFiles = $db->fetchAll("
    SELECT pf.*, u.full_name as uploaded_by_name 
    FROM payroll_files pf 
    LEFT JOIN users u ON pf.uploaded_by = u.id 
    WHERE pf.is_active = 1 
    ORDER BY pf.upload_date DESC 
    LIMIT 10
");

// Get recent activity
$recentActivity = $db->fetchAll("
    SELECT al.*, u.full_name 
    FROM activity_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    ORDER BY al.created_at DESC 
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><?php ; ?> TWINHILL PAYROLL SYSTEM</a>
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
                <a class="nav-link active" href="dashboard.php">
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
                    <h1 class="h2">Dashboard</h1>
                </div>

                <?php displayAlert(); ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title">Total Files</h5>
                                        <h2><?php echo $totalFiles; ?></h2>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-files fs-1"></i>
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
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title">Total Downloads</h5>
                                        <h2><?php echo $totalDownloads; ?></h2>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-download fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Recent Files -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Recent Files</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentFiles)): ?>
                                    <p class="text-muted">No files uploaded yet.</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recentFiles as $file): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($file['title']); ?></h6>
                                                    <small><?php echo formatDate($file['upload_date'], 'M j, Y'); ?></small>
                                                </div>
                                                <p class="mb-1">
                                                    <small class="text-muted">
                                                        By: <?php echo htmlspecialchars($file['uploaded_by_name'] ?? 'Unknown'); ?> |
                                                        Size: <?php echo formatFileSize($file['file_size']); ?> |
                                                        Downloads: <?php echo $file['download_count']; ?>
                                                    </small>
                                                </p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="text-center mt-3">
                                    <a href="files.php" class="btn btn-sm btn-outline-primary">View All Files</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Recent Activity</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentActivity)): ?>
                                    <p class="text-muted">No recent activity.</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recentActivity as $activity): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($activity['action']); ?></h6>
                                                    <small><?php echo formatDate($activity['created_at'], 'M j, H:i'); ?></small>
                                                </div>
                                                <p class="mb-1">
                                                    <small><?php echo htmlspecialchars($activity['description']); ?></small>
                                                </p>
                                                <small class="text-muted">
                                                    By: <?php echo htmlspecialchars($activity['full_name'] ?? 'System'); ?>
                                                </small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="text-center mt-3">
                                    <a href="logs.php" class="btn btn-sm btn-outline-primary">View All Logs</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
