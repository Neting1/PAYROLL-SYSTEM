<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/database.php';

requireLogin();

$db = Database::getInstance();

// Get files accessible to this user
$sql = "SELECT DISTINCT pf.*, u.full_name as uploaded_by_name 
        FROM payroll_files pf 
        LEFT JOIN users u ON pf.uploaded_by = u.id
        LEFT JOIN file_access fa ON pf.id = fa.file_id 
        WHERE pf.is_active = 1 
        AND (fa.user_id = ? OR ? IN (SELECT id FROM users WHERE role = 'admin'))
        ORDER BY pf.upload_date DESC";

$files = $db->fetchAll($sql, [$_SESSION['user_id'], $_SESSION['user_id']]);

// Get user stats
$totalDownloads = $db->fetch("
    SELECT COUNT(*) as count 
    FROM download_logs dl 
    JOIN payroll_files pf ON dl.file_id = pf.id 
    WHERE dl.user_id = ? AND pf.is_active = 1
", [$_SESSION['user_id']])['count'];

$availableFiles = count($files);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Payroll System Custom Styles */
        /* Simple, Clean Body Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            padding-top: 56px; /* Space for fixed navbar */
        }

        /* Simple Fixed Navbar */
        .navbar {
            position: fixed !important;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            height: 56px;
        }

        /* Clean Static Sidebar */
        .sidebar {
            position: fixed !important;
            top: 56px;
            left: 0;
            width: 250px;
            height: calc(100vh - 56px);
            background-color: #f8f9fa;
            border-right: 1px solid #dee2e6;
            overflow-y: auto;
            z-index: 1020;
            display: flex !important;
            flex-direction: column;
            padding-top: 1rem;
        }

        /* Simple Main Content */
        .main-content {
            margin-left: 250px;
            padding: 1.5rem;
            min-height: calc(100vh - 56px);
        }

        /* Enhanced Sidebar Navigation */
        .sidebar .nav-link {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            margin: 0.125rem 0.75rem;
            color: #495057;
            text-decoration: none;
            border-radius: 0.75rem;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .sidebar .nav-link:hover {
            color: #0d6efd;
            background: linear-gradient(135deg, rgba(13, 110, 253, 0.1), rgba(13, 110, 253, 0.05));
            transform: translateX(4px);
            box-shadow: 0 2px 8px rgba(13, 110, 253, 0.15);
        }

        .sidebar .nav-link.active {
            color: #fff;
            background: linear-gradient(135deg, #0d6efd, #0056b3);
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.4);
            transform: translateX(6px);
        }

        .sidebar .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: #fff;
            border-radius: 0 4px 4px 0;
        }

        .sidebar .nav-link i {
            margin-right: 0.75rem;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
            transition: transform 0.2s ease;
        }

        .sidebar .nav-link:hover i {
            transform: scale(1.1);
        }

        .sidebar .nav-link.active i {
            transform: scale(1.05);
        }

        /* Card Enhancements */
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border-radius: 0.5rem;
            transition: box-shadow 0.15s ease-in-out;
        }

        .card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
        }

        /* Statistics Cards */
        .card.text-white .card-title {
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            opacity: 0.8;
        }

        .card.text-white h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0;
        }

        /* Table Enhancements */
        .table {
            margin-bottom: 0;
        }

        .table th {
            border-top: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            color: #6c757d;
            padding: 1rem 0.75rem;
        }

        .table td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(13, 110, 253, 0.05);
        }

        /* Sticky Table Headers */
        .table-responsive {
            max-height: 600px;
            overflow-y: auto;
            /* Improve scrolling performance */
            -webkit-overflow-scrolling: touch;
            scroll-behavior: smooth;
        }

        .table-responsive .table thead th {
            /* Cross-browser sticky positioning */
            position: -webkit-sticky;
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.4);
            /* Prevent background bleeding */
            background-clip: padding-box;
        }

        .table-responsive .table thead th.table-light {
            background-color: #f8f9fa;
        }

        /* Badges */
        .badge {
            font-weight: 500;
            padding: 0.375em 0.75em;
        }

        /* Buttons */
        .btn {
            border-radius: 0.375rem;
            font-weight: 500;
            transition: all 0.15s ease-in-out;
        }

        .btn-group .btn {
            border-radius: 0.375rem;
        }

        .btn-group .btn:not(:last-child) {
            margin-right: 0.25rem;
        }

        /* Perfect Mobile Responsive */
        @media (max-width: 768px) {
            body {
                padding-top: 0;
            }
            
            .navbar {
                position: relative !important;
            }
            
            .sidebar {
                position: relative !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                height: auto !important;
                border-right: none;
                border-bottom: 2px solid #e9ecef;
                box-shadow: 0 2px 8px rgba(0,0,0,.1);
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 1.5rem;
            }
            
            .app-container {
                padding-top: 0;
                flex-direction: column;
            }
            
            .sidebar .nav {
                display: flex;
                padding: 0.5rem;
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .sidebar .nav-item {
                flex-shrink: 0;
                margin: 0 0.25rem;
            }
            
            .sidebar .nav-link {
                padding: 0.75rem 1rem;
                margin: 0;
                min-width: auto;
                text-align: center;
                flex-direction: column;
                font-size: 0.85rem;
            }
            
            .sidebar .nav-link i {
                margin: 0 0 0.25rem 0;
                font-size: 1.2rem;
            }
            
            .table-responsive {
                font-size: 0.875rem;
            }
            
            .btn-group {
                display: flex;
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .btn-group .btn {
                margin-right: 0;
            }
            
            .card.text-white h2 {
                font-size: 2rem;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 1rem;
            }
            
            .sidebar .nav-link {
                font-size: 0.8rem;
                padding: 0.6rem 0.8rem;
            }
        }

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .slide-up {
            animation: slideUp 0.3s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Custom Scrollbar */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Table scrollbar */
        .table-responsive::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        .table-responsive::-webkit-scrollbar-corner {
            background: #f1f1f1;
        }

        /* Focus Indicators for Accessibility */
        .btn:focus,
        .form-control:focus,
        .form-check-input:focus {
            outline: 2px solid #0d6efd;
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><?php echo APP_NAME; ?></a>
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
                <a class="nav-link" href="#" onclick="window.location.reload()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </li>
        </ul>
        
        <!-- User Info Section -->
        <div class="mt-auto p-3 border-top">
            <div class="d-flex align-items-center">
                <i class="bi bi-person-circle fs-4 me-2 text-primary"></i>
                <div class="flex-grow-1">
                    <div class="fw-semibold"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                    <div class="small text-muted">Standard User</div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">My Payroll Files</h1>
        </div>

        <?php displayAlert(); ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Available Files</h5>
                                <h2><?php echo $availableFiles; ?></h2>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-files fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">My Downloads</h5>
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

        <!-- Files List -->
        <div class="card slide-up">
            <div class="card-header">
                <h5 class="mb-0">Payroll Files</h5>
            </div>
            <div class="card-body">
                <?php if (empty($files)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox fs-1 text-muted"></i>
                        <h4 class="text-muted mt-3">No files available</h4>
                        <p class="text-muted">There are no payroll files available for you at this time.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Pay Period</th>
                                    <th>Upload Date</th>
                                    <th>File Size</th>
                                    <th>Downloads</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($files as $file): ?>
                                    <tr class="fade-in">
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($file['title']); ?></strong>
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
                                            <br><small class="text-muted"><?php echo formatDate($file['upload_date'], 'g:i A'); ?></small>
                                        </td>
                                        <td><?php echo formatFileSize($file['file_size']); ?></td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo $file['download_count']; ?></span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="download.php?id=<?php echo $file['id']; ?>" 
                                                   class="btn btn-sm btn-primary" title="Download">
                                                    <i class="bi bi-download"></i> Download
                                                </a>
                                                <a href="view.php?id=<?php echo $file['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" 
                                                   target="_blank" title="View in browser">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
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

        <?php if (!empty($files)): ?>
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6><i class="bi bi-info-circle"></i> How to access your files:</h6>
                            <ul class="mb-0">
                                <li><strong>Download:</strong> Click the "Download" button to save the PDF file to your computer</li>
                                <li><strong>View:</strong> Click the "View" button to open the PDF file in your browser</li>
                                <li><strong>File Access:</strong> You can only see and download files that have been shared with you</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add some interactive elements
        document.addEventListener('DOMContentLoaded', function() {
            // Add animation to cards on hover
            const cards = document.querySelectorAll('.card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', () => {
                    card.style.transform = 'translateY(-5px)';
                });
                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'translateY(0)';
                });
            });
            
            // Handle mobile menu toggle
            const sidebar = document.querySelector('.sidebar');
            if (window.innerWidth <= 768) {
                // Make sidebar initially hidden on mobile
                sidebar.style.display = 'none';
                
                // Create toggle button
                const toggleBtn = document.createElement('button');
                toggleBtn.className = 'btn btn-primary position-fixed';
                toggleBtn.style.bottom = '20px';
                toggleBtn.style.right = '20px';
                toggleBtn.style.zIndex = '1040';
                toggleBtn.innerHTML = '<i class="bi bi-list"></i>';
                toggleBtn.onclick = function() {
                    sidebar.style.display = sidebar.style.display === 'none' ? 'block' : 'none';
                };
                document.body.appendChild(toggleBtn);
            }
        });
    </script>
</body>
</html>