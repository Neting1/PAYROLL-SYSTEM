<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

requireAdmin();

$db = Database::getInstance();

// Pagination settings
$logsPerPage = 50;
$currentPage = max(1, intval($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $logsPerPage;

// Filter settings
$actionFilter = $_GET['action'] ?? '';
$userFilter = $_GET['user'] ?? '';
$dateFilter = $_GET['date'] ?? '';
$searchFilter = $_GET['search'] ?? '';

// Build WHERE clause
$whereConditions = [];
$params = [];

if (!empty($actionFilter)) {
    $whereConditions[] = "al.action = ?";
    $params[] = $actionFilter;
}

if (!empty($userFilter) && is_numeric($userFilter)) {
    $whereConditions[] = "al.user_id = ?";
    $params[] = $userFilter;
}

if (!empty($dateFilter)) {
    $whereConditions[] = "DATE(al.created_at) = ?";
    $params[] = $dateFilter;
}

if (!empty($searchFilter)) {
    $whereConditions[] = "(al.description LIKE ? OR al.action LIKE ? OR u.full_name LIKE ?)";
    $searchTerm = '%' . $searchFilter . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count for pagination
$countSql = "
    SELECT COUNT(*) as total
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    $whereClause
";
$totalLogs = $db->fetch($countSql, $params)['total'];
$totalPages = ceil($totalLogs / $logsPerPage);

// Get activity logs with pagination
$logsSql = "
    SELECT al.*, 
           u.full_name, 
           u.username,
           u.role
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    $whereClause
    ORDER BY al.created_at DESC
    LIMIT $logsPerPage OFFSET $offset
";

$logs = $db->fetchAll($logsSql, $params);

// Get filter options
$actions = $db->fetchAll("SELECT DISTINCT action FROM activity_logs ORDER BY action");
$users = $db->fetchAll("SELECT id, full_name, username FROM users WHERE is_active = 1 ORDER BY full_name");

// Get statistics
$stats = [
    'total_logs' => $totalLogs,
    'today_logs' => $db->fetch("SELECT COUNT(*) as count FROM activity_logs WHERE DATE(created_at) = CURDATE()")['count'],
    'unique_users' => $db->fetch("SELECT COUNT(DISTINCT user_id) as count FROM activity_logs WHERE user_id IS NOT NULL")['count'],
    'recent_downloads' => $db->fetch("SELECT COUNT(*) as count FROM activity_logs WHERE action = 'file_download' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")['count']
];

// Handle log cleanup (delete old logs)
if ($_POST['action'] ?? '' === 'cleanup_logs' && validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $daysToKeep = intval($_POST['days_to_keep'] ?? 90);
    if ($daysToKeep > 0 && $daysToKeep <= 365) {
        try {
            $deletedCount = $db->execute(
                "DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$daysToKeep]
            );
            
            logActivity($_SESSION['user_id'], 'logs_cleanup', "Cleaned up logs older than $daysToKeep days");
            showAlert("Successfully cleaned up old activity logs.", 'success');
        } catch (Exception $e) {
            showAlert('Failed to cleanup logs.', 'danger');
            error_log("Log cleanup error: " . $e->getMessage());
        }
    } else {
        showAlert('Invalid cleanup period. Please enter a value between 1 and 365 days.', 'danger');
    }
    redirect('logs.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - <?php echo APP_NAME; ?></title>
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
                <a class="nav-link active" href="logs.php">
                    <i class="bi bi-list-ul"></i> Activity Logs
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Activity Logs</h1>
                    <div class="btn-toolbar">
                        <button type="button" class="btn btn-outline-danger btn-sm me-2" data-bs-toggle="modal" data-bs-target="#cleanupModal">
                            <i class="bi bi-trash"></i> Cleanup Logs
                        </button>
                        <a href="logs.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </a>
                    </div>
                </div>

                <?php displayAlert(); ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Total Logs</h6>
                                        <h3><?php echo number_format($stats['total_logs']); ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-list-ul fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Today's Activity</h6>
                                        <h3><?php echo number_format($stats['today_logs']); ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-calendar-check fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Active Users</h6>
                                        <h3><?php echo number_format($stats['unique_users']); ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-people fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Recent Downloads</h6>
                                        <h3><?php echo number_format($stats['recent_downloads']); ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-download fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="action" class="form-label">Action</label>
                                <select class="form-select" id="action" name="action">
                                    <option value="">All Actions</option>
                                    <?php foreach ($actions as $action): ?>
                                        <option value="<?php echo htmlspecialchars($action['action']); ?>" 
                                                <?php echo $actionFilter === $action['action'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $action['action']))); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="user" class="form-label">User</label>
                                <select class="form-select" id="user" name="user">
                                    <option value="">All Users</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" 
                                                <?php echo $userFilter == $user['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="date" name="date" 
                                       value="<?php echo htmlspecialchars($dateFilter); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Search in descriptions..." 
                                       value="<?php echo htmlspecialchars($searchFilter); ?>">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Apply Filters
                                </button>
                                <a href="logs.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i> Clear Filters
                                </a>
                                <span class="text-muted ms-3">
                                    Showing <?php echo number_format($totalLogs); ?> log entries
                                </span>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Activity Logs Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>Activity Log Entries</h5>
                        <?php if ($totalPages > 1): ?>
                            <span class="badge bg-secondary">
                                Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($logs)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-journal-x fs-1 text-muted"></i>
                                <h4 class="text-muted mt-3">No logs found</h4>
                                <p class="text-muted">No activity logs match your current filters.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Timestamp</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Description</th>
                                            <th>IP Address</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($logs as $log): ?>
                                            <tr>
                                                <td>
                                                    <?php echo formatDate($log['created_at'], 'M j, Y'); ?>
                                                    <br><small class="text-muted"><?php echo formatDate($log['created_at'], 'g:i:s A'); ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($log['full_name']): ?>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($log['full_name']); ?></strong>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($log['username']); ?></small>
                                                            <?php if ($log['role'] === 'admin'): ?>
                                                                <br><span class="badge bg-danger badge-sm">Admin</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">System</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                                  <?php 
                                                    switch($log['action']) {
                                                        case 'login':
                                                            $actionClass = 'bg-success';
                                                            break;
                                                        case 'logout':
                                                            $actionClass = 'bg-secondary';
                                                            break;
                                                        case 'failed_login':
                                                            $actionClass = 'bg-danger';
                                                            break;
                                                        case 'file_upload':
                                                            $actionClass = 'bg-primary';
                                                            break;
                                                        case 'file_download':
                                                            $actionClass = 'bg-info';
                                                            break;
                                                        case 'file_view':
                                                            $actionClass = 'bg-light text-dark';
                                                            break;
                                                        case 'file_delete':
                                                            $actionClass = 'bg-warning text-dark';
                                                            break;
                                                        case 'user_created':
                                                            $actionClass = 'bg-success';
                                                            break;
                                                        case 'user_status_changed':
                                                            $actionClass = 'bg-warning text-dark';
                                                            break;
                                                        case 'password_reset':
                                                            $actionClass = 'bg-danger';
                                                            break;
                                                        case 'access_granted':
                                                            $actionClass = 'bg-success';
                                                            break;
                                                        case 'access_revoked':
                                                            $actionClass = 'bg-warning text-dark';
                                                            break;
                                                        default:
                                                            $actionClass = 'bg-secondary';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $actionClass; ?>">
                                                        <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $log['action']))); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="text-truncate" style="max-width: 300px;" 
                                                         title="<?php echo htmlspecialchars($log['description']); ?>">
                                                        <?php echo htmlspecialchars($log['description']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <code><?php echo htmlspecialchars($log['ip_address'] ?: 'N/A'); ?></code>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Log pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php
                            // Build query string for pagination links
                            $queryParams = array_filter([
                                'action' => $actionFilter,
                                'user' => $userFilter,
                                'date' => $dateFilter,
                                'search' => $searchFilter
                            ]);
                            $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                            ?>
                            
                            <?php if ($currentPage > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1<?php echo $queryString; ?>">First</a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $currentPage - 1; ?><?php echo $queryString; ?>">Previous</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $currentPage - 2);
                            $endPage = min($totalPages, $currentPage + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <li class="page-item <?php echo $i === $currentPage ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $queryString; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($currentPage < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $currentPage + 1; ?><?php echo $queryString; ?>">Next</a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo $queryString; ?>">Last</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
    </main>

    <!-- Cleanup Logs Modal -->
    <div class="modal fade" id="cleanupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cleanup Old Logs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="cleanup_logs">
                        
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Warning:</strong> This action will permanently delete old activity logs and cannot be undone.
                        </div>
                        
                        <div class="mb-3">
                            <label for="days_to_keep" class="form-label">Keep logs from the last:</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="days_to_keep" name="days_to_keep" 
                                       value="90" min="1" max="365" required>
                                <span class="input-group-text">days</span>
                            </div>
                            <div class="form-text">
                                Logs older than this will be permanently deleted. Recommended: 90 days.
                            </div>
                        </div>
                        
                        <p><strong>Current total logs:</strong> <?php echo number_format($stats['total_logs']); ?></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Cleanup Logs
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
