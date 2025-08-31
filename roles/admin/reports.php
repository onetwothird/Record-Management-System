<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Check if user is logged in and is admin or super_admin
requireAdmin();

// Get current user's department for filtering (if not super_admin)
$current_user_dept = null;
if ($_SESSION['role'] !== 'super_admin') {
    $user_stmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
    $user_stmt->execute([$_SESSION['user_id']]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    $current_user_dept = $user_data['department_id'];
    
    // If admin has no department, they can't access reports
    if (!$current_user_dept) {
        header('Location: dashboard.php?error=no_department');
        exit;
    }
}

// Handle report generation
$report_type = $_GET['type'] ?? 'overview';
$date_range = $_GET['range'] ?? '30';

// Date range calculation
$end_date = date('Y-m-d');
switch ($date_range) {
    case '7':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $range_label = 'Last 7 Days';
        break;
    case '30':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $range_label = 'Last 30 Days';
        break;
    case '90':
        $start_date = date('Y-m-d', strtotime('-90 days'));
        $range_label = 'Last 90 Days';
        break;
    case '365':
        $start_date = date('Y-m-d', strtotime('-365 days'));
        $range_label = 'Last Year';
        break;
    case 'custom':
        $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $end_date = $_GET['end_date'] ?? date('Y-m-d');
        $range_label = 'Custom Range';
        break;
    default:
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $range_label = 'Last 30 Days';
}

// Get overview statistics (filtered by department for admins)
function getOverviewStats($pdo, $start_date, $end_date, $department_filter = null) {
    $stats = [];
    
    // Files statistics
    if ($department_filter) {
        $file_query = "SELECT 
                          COUNT(*) as total_files,
                          COUNT(CASE WHEN DATE(f.uploaded_at) >= ? AND DATE(f.uploaded_at) <= ? THEN 1 END) as new_files,
                          SUM(f.file_size) as total_size,
                          COUNT(CASE WHEN f.is_deleted = 1 THEN 1 END) as deleted_files
                       FROM files f
                       INNER JOIN folders fo ON f.folder_id = fo.id
                       WHERE fo.department_id = ?";
        $stmt = $pdo->prepare($file_query);
        $stmt->execute([$start_date, $end_date, $department_filter]);
    } else {
        $file_query = "SELECT 
                          COUNT(*) as total_files,
                          COUNT(CASE WHEN DATE(uploaded_at) >= ? AND DATE(uploaded_at) <= ? THEN 1 END) as new_files,
                          SUM(file_size) as total_size,
                          COUNT(CASE WHEN is_deleted = 1 THEN 1 END) as deleted_files
                       FROM files";
        $stmt = $pdo->prepare($file_query);
        $stmt->execute([$start_date, $end_date]);
    }
    $stats['files'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Folders statistics
    if ($department_filter) {
        $folder_query = "SELECT 
                            COUNT(*) as total_folders,
                            COUNT(CASE WHEN DATE(created_at) >= ? AND DATE(created_at) <= ? THEN 1 END) as new_folders,
                            COUNT(CASE WHEN is_deleted = 1 THEN 1 END) as deleted_folders
                         FROM folders
                         WHERE department_id = ?";
        $stmt = $pdo->prepare($folder_query);
        $stmt->execute([$start_date, $end_date, $department_filter]);
    } else {
        $folder_query = "SELECT 
                            COUNT(*) as total_folders,
                            COUNT(CASE WHEN DATE(created_at) >= ? AND DATE(created_at) <= ? THEN 1 END) as new_folders,
                            COUNT(CASE WHEN is_deleted = 1 THEN 1 END) as deleted_folders
                         FROM folders";
        $stmt = $pdo->prepare($folder_query);
        $stmt->execute([$start_date, $end_date]);
    }
    $stats['folders'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Users statistics
    if ($department_filter) {
        $user_query = "SELECT 
                          COUNT(*) as total_users,
                          COUNT(CASE WHEN DATE(created_at) >= ? AND DATE(created_at) <= ? THEN 1 END) as new_users,
                          COUNT(CASE WHEN is_approved = 1 THEN 1 END) as approved_users,
                          COUNT(CASE WHEN last_login >= ? AND last_login <= ? THEN 1 END) as active_users
                       FROM users
                       WHERE department_id = ?";
        $stmt = $pdo->prepare($user_query);
        $stmt->execute([$start_date, $end_date, $start_date . ' 00:00:00', $end_date . ' 23:59:59', $department_filter]);
    } else {
        $user_query = "SELECT 
                          COUNT(*) as total_users,
                          COUNT(CASE WHEN DATE(created_at) >= ? AND DATE(created_at) <= ? THEN 1 END) as new_users,
                          COUNT(CASE WHEN is_approved = 1 THEN 1 END) as approved_users,
                          COUNT(CASE WHEN last_login >= ? AND last_login <= ? THEN 1 END) as active_users
                       FROM users";
        $stmt = $pdo->prepare($user_query);
        $stmt->execute([$start_date, $end_date, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    }
    $stats['users'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Document requests statistics
    if ($department_filter) {
        $doc_query = "SELECT 
                         COUNT(*) as total_requests,
                         COUNT(CASE WHEN DATE(dr.created_at) >= ? AND DATE(dr.created_at) <= ? THEN 1 END) as new_requests,
                         COUNT(CASE WHEN dr.status = 'completed' THEN 1 END) as completed_requests,
                         COUNT(CASE WHEN dr.status = 'pending' THEN 1 END) as pending_requests
                      FROM document_requests dr
                      INNER JOIN users u ON dr.user_id = u.id
                      WHERE u.department_id = ?";
        $stmt = $pdo->prepare($doc_query);
        $stmt->execute([$start_date, $end_date, $department_filter]);
    } else {
        $doc_query = "SELECT 
                         COUNT(*) as total_requests,
                         COUNT(CASE WHEN DATE(created_at) >= ? AND DATE(created_at) <= ? THEN 1 END) as new_requests,
                         COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_requests,
                         COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_requests
                      FROM document_requests";
        $stmt = $pdo->prepare($doc_query);
        $stmt->execute([$start_date, $end_date]);
    }
    $stats['documents'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Shares statistics
    if ($department_filter) {
        $share_query = "SELECT 
                           COUNT(*) as total_shares,
                           COUNT(CASE WHEN DATE(fs.created_at) >= ? AND DATE(fs.created_at) <= ? THEN 1 END) as new_shares,
                           COUNT(CASE WHEN fs.is_active = 1 THEN 1 END) as active_shares,
                           SUM(fs.download_count) as total_downloads
                        FROM file_shares fs
                        INNER JOIN files f ON fs.file_id = f.id
                        INNER JOIN folders fo ON f.folder_id = fo.id
                        WHERE fo.department_id = ?";
        $stmt = $pdo->prepare($share_query);
        $stmt->execute([$start_date, $end_date, $department_filter]);
    } else {
        $share_query = "SELECT 
                           COUNT(*) as total_shares,
                           COUNT(CASE WHEN DATE(created_at) >= ? AND DATE(created_at) <= ? THEN 1 END) as new_shares,
                           COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_shares,
                           SUM(download_count) as total_downloads
                        FROM file_shares";
        $stmt = $pdo->prepare($share_query);
        $stmt->execute([$start_date, $end_date]);
    }
    $stats['shares'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $stats;
}

// Get department-wise statistics (filtered for admins)
function getDepartmentStats($pdo, $start_date, $end_date, $department_filter = null) {
    if ($department_filter) {
        // For regular admins, only show their department
        $query = "SELECT 
                     d.department_name, d.department_code,
                     COUNT(DISTINCT u.id) as user_count,
                     COUNT(DISTINCT f.id) as file_count,
                     COUNT(DISTINCT fo.id) as folder_count,
                     COALESCE(SUM(f.file_size), 0) as total_size
                  FROM departments d
                  LEFT JOIN users u ON d.id = u.department_id AND u.created_at BETWEEN ? AND ?
                  LEFT JOIN folders fo ON d.id = fo.department_id
                  LEFT JOIN files f ON fo.id = f.folder_id AND f.uploaded_at BETWEEN ? AND ?
                  WHERE d.is_active = 1 AND d.id = ?
                  GROUP BY d.id, d.department_name, d.department_code
                  ORDER BY file_count DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $start_date . ' 00:00:00', $end_date . ' 23:59:59', 
            $start_date . ' 00:00:00', $end_date . ' 23:59:59',
            $department_filter
        ]);
    } else {
        // For super admins, show all departments
        $query = "SELECT 
                     d.department_name, d.department_code,
                     COUNT(DISTINCT u.id) as user_count,
                     COUNT(DISTINCT f.id) as file_count,
                     COUNT(DISTINCT fo.id) as folder_count,
                     COALESCE(SUM(f.file_size), 0) as total_size
                  FROM departments d
                  LEFT JOIN users u ON d.id = u.department_id AND u.created_at BETWEEN ? AND ?
                  LEFT JOIN folders fo ON d.id = fo.department_id
                  LEFT JOIN files f ON fo.id = f.folder_id AND f.uploaded_at BETWEEN ? AND ?
                  WHERE d.is_active = 1
                  GROUP BY d.id, d.department_name, d.department_code
                  ORDER BY file_count DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59', $start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get activity logs (filtered for admins)
function getActivityLogs($pdo, $start_date, $end_date, $limit = 50, $department_filter = null) {
    if ($department_filter) {
        $query = "SELECT 
                     al.*, u.username, u.name, u.surname,
                     CONCAT(u.name, ' ', COALESCE(u.mi, ''), ' ', u.surname) as full_name
                  FROM activity_logs al
                  LEFT JOIN users u ON al.user_id = u.id
                  WHERE DATE(al.created_at) BETWEEN ? AND ? 
                  AND (u.department_id = ? OR u.department_id IS NULL)
                  ORDER BY al.created_at DESC";
    } else {
        $query = "SELECT 
                     al.*, u.username, u.name, u.surname,
                     CONCAT(u.name, ' ', COALESCE(u.mi, ''), ' ', u.surname) as full_name
                  FROM activity_logs al
                  LEFT JOIN users u ON al.user_id = u.id
                  WHERE DATE(al.created_at) BETWEEN ? AND ?
                  ORDER BY al.created_at DESC";
    }
    
    // Add LIMIT clause only if limit is provided
    if ($limit > 0) {
        $query .= " LIMIT " . (int)$limit;
    }
    
    $stmt = $pdo->prepare($query);
    if ($department_filter) {
        $stmt->execute([$start_date, $end_date, $department_filter]);
    } else {
        $stmt->execute([$start_date, $end_date]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get top users by activity (filtered for admins)
function getTopUsers($pdo, $start_date, $end_date, $department_filter = null) {
    if ($department_filter) {
        $query = "SELECT 
                     u.username, u.name, u.surname, d.department_name,
                     CONCAT(u.name, ' ', COALESCE(u.mi, ''), ' ', u.surname) as full_name,
                     COUNT(DISTINCT f.id) as files_uploaded,
                     COUNT(DISTINCT al.id) as activities,
                     COALESCE(SUM(f.file_size), 0) as total_uploaded_size
                  FROM users u
                  LEFT JOIN files f ON u.id = f.uploaded_by AND f.uploaded_at BETWEEN ? AND ?
                  LEFT JOIN activity_logs al ON u.id = al.user_id AND al.created_at BETWEEN ? AND ?
                  LEFT JOIN departments d ON u.department_id = d.id
                  WHERE u.role IN ('user', 'admin') AND u.department_id = ?
                  GROUP BY u.id
                  HAVING files_uploaded > 0 OR activities > 0
                  ORDER BY activities DESC, files_uploaded DESC
                  LIMIT 20";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $start_date . ' 00:00:00', $end_date . ' 23:59:59',
            $start_date . ' 00:00:00', $end_date . ' 23:59:59',
            $department_filter
        ]);
    } else {
        $query = "SELECT 
                     u.username, u.name, u.surname, d.department_name,
                     CONCAT(u.name, ' ', COALESCE(u.mi, ''), ' ', u.surname) as full_name,
                     COUNT(DISTINCT f.id) as files_uploaded,
                     COUNT(DISTINCT al.id) as activities,
                     COALESCE(SUM(f.file_size), 0) as total_uploaded_size
                  FROM users u
                  LEFT JOIN files f ON u.id = f.uploaded_by AND f.uploaded_at BETWEEN ? AND ?
                  LEFT JOIN activity_logs al ON u.id = al.user_id AND al.created_at BETWEEN ? AND ?
                  LEFT JOIN departments d ON u.department_id = d.id
                  WHERE u.role IN ('user', 'admin')
                  GROUP BY u.id
                  HAVING files_uploaded > 0 OR activities > 0
                  ORDER BY activities DESC, files_uploaded DESC
                  LIMIT 20";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $start_date . ' 00:00:00', $end_date . ' 23:59:59',
            $start_date . ' 00:00:00', $end_date . ' 23:59:59'
        ]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Generate data based on report type
$data = [];
switch ($report_type) {
    case 'overview':
        $data['stats'] = getOverviewStats($pdo, $start_date, $end_date, $current_user_dept);
        $data['departments'] = getDepartmentStats($pdo, $start_date, $end_date, $current_user_dept);
        $data['activities'] = getActivityLogs($pdo, $start_date, $end_date, 20, $current_user_dept);
        break;
    case 'departments':
        $data['departments'] = getDepartmentStats($pdo, $start_date, $end_date, $current_user_dept);
        break;
    case 'users':
        $data['top_users'] = getTopUsers($pdo, $start_date, $end_date, $current_user_dept);
        break;
    case 'activities':
        $data['activities'] = getActivityLogs($pdo, $start_date, $end_date, 100, $current_user_dept);
        break;
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

// Get current department name for display
$department_name = 'All Departments';
if ($current_user_dept) {
    $dept_stmt = $pdo->prepare("SELECT department_name FROM departments WHERE id = ?");
    $dept_stmt->execute([$current_user_dept]);
    $dept_data = $dept_stmt->fetch(PDO::FETCH_ASSOC);
    $department_name = $dept_data['department_name'] ?? 'Unknown Department';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?= time() ?>">
    <style>
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .report-nav {
            border-bottom: 1px solid #dee2e6;
        }
        .report-nav .nav-link.active {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
        }
        .activity-item {
            border-left: 3px solid #007bff;
            padding-left: 15px;
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .department-badge {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Sidebar -->
    <?php include 'components/sidebar.html'; ?>
    
    <!-- Content -->
    <section id="content">
        <!-- Navbar -->
        <?php include 'components/navbar.html'; ?>

        <div class="container-fluid">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center bg-white p-3 rounded shadow-sm" style="margin-top: 15px;">
                        <div>
                            <h2 class="mb-0"><i class="fas fa-chart-bar me-2 text-success" style = "margin-bottom: 14px;"></i>Reports & Analytics</h2>
                            <?php if ($_SESSION['role'] !== 'super_admin'): ?>
                                <div class="mt-2">
                                    <span class="department-badge">
                                        <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($department_name); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-primary" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                            <button class="btn btn-primary" onclick="exportReport()">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Date Range Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <input type="hidden" name="type" value="<?php echo $report_type; ?>">
                        <div class="col-md-3">
                            <label class="form-label">Date Range</label>
                            <select class="form-select" name="range" onchange="toggleCustomDates(this.value)">
                                <option value="7" <?php echo $date_range == '7' ? 'selected' : ''; ?>>Last 7 Days</option>
                                <option value="30" <?php echo $date_range == '30' ? 'selected' : ''; ?>>Last 30 Days</option>
                                <option value="90" <?php echo $date_range == '90' ? 'selected' : ''; ?>>Last 90 Days</option>
                                <option value="365" <?php echo $date_range == '365' ? 'selected' : ''; ?>>Last Year</option>
                                <option value="custom" <?php echo $date_range == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                            </select>
                        </div>
                        <div class="col-md-3" id="start-date-col" style="display: <?php echo $date_range == 'custom' ? 'block' : 'none'; ?>;">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-3" id="end-date-col" style="display: <?php echo $date_range == 'custom' ? 'block' : 'none'; ?>;">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-sync-alt"></i> Update Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Report Period Info -->
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Showing data for: <strong><?php echo $range_label; ?></strong> 
                (<?php echo date('M j, Y', strtotime($start_date)); ?> to <?php echo date('M j, Y', strtotime($end_date)); ?>)
                <?php if ($_SESSION['role'] !== 'super_admin'): ?>
                    <br><i class="fas fa-filter me-2"></i>
                    Filtered to <strong><?php echo htmlspecialchars($department_name); ?></strong> department only
                <?php endif; ?>
            </div>

            <!-- Report Type Navigation -->
            <div class="card mb-4">
                <div class="card-body">
                    <ul class="nav nav-pills report-nav" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $report_type === 'overview' ? 'active' : ''; ?>" 
                               href="?type=overview&range=<?php echo $date_range; ?>">
                                <i class="fas fa-tachometer-alt me-2"></i>Overview
                            </a>
                        </li>
                        <?php if ($_SESSION['role'] === 'super_admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $report_type === 'departments' ? 'active' : ''; ?>" 
                               href="?type=departments&range=<?php echo $date_range; ?>">
                                <i class="fas fa-building me-2"></i>Departments
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $report_type === 'users' ? 'active' : ''; ?>" 
                               href="?type=users&range=<?php echo $date_range; ?>">
                                <i class="fas fa-users me-2"></i>Users
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Overview Report -->
            <?php if ($report_type === 'overview'): ?>
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card stat-card text-white bg-primary">
                            <div class="card-body text-center">
                                <i class="fas fa-file fa-2x mb-2"></i>
                                <h4><?php echo number_format($data['stats']['files']['total_files']); ?></h4>
                                <small>Total Files</small>
                                <div class="mt-2">
                                    <span class="badge bg-light text-primary">+<?php echo number_format($data['stats']['files']['new_files']); ?> new</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card text-white bg-warning">
                            <div class="card-body text-center">
                                <i class="fas fa-folder fa-2x mb-2"></i>
                                <h4><?php echo number_format($data['stats']['folders']['total_folders']); ?></h4>
                                <small>Total Folders</small>
                                <div class="mt-2">
                                    <span class="badge bg-light text-warning">+<?php echo number_format($data['stats']['folders']['new_folders']); ?> new</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card text-white bg-success">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <h4><?php echo number_format($data['stats']['users']['total_users']); ?></h4>
                                <small>Total Users</small>
                                <div class="mt-2">
                                    <span class="badge bg-light text-success">+<?php echo number_format($data['stats']['users']['new_users']); ?> new</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card text-white bg-info">
                            <div class="card-body text-center">
                                <i class="fas fa-file-alt fa-2x mb-2"></i>
                                <h4><?php echo number_format($data['stats']['documents']['total_requests']); ?></h4>
                                <small>Document Requests</small>
                                <div class="mt-2">
                                    <span class="badge bg-light text-info">+<?php echo number_format($data['stats']['documents']['new_requests']); ?> new</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card text-white bg-secondary">
                            <div class="card-body text-center">
                                <i class="fas fa-share-alt fa-2x mb-2"></i>
                                <h4><?php echo number_format($data['stats']['shares']['total_shares']); ?></h4>
                                <small>File Shares</small>
                                <div class="mt-2">
                                    <span class="badge bg-light text-secondary">+<?php echo number_format($data['stats']['shares']['new_shares']); ?> new</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card stat-card text-white bg-dark">
                            <div class="card-body text-center">
                                <i class="fas fa-hdd fa-2x mb-2"></i>
                                <h4><?php echo formatFileSize($data['stats']['files']['total_size']); ?></h4>
                                <small>Storage Used</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-pie me-2"></i>File Distribution</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="fileDistributionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-chart-bar me-2"></i>
                                    <?php echo $_SESSION['role'] === 'super_admin' ? 'Department Activity' : 'User Activity'; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="departmentActivityChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                
            <?php endif; ?>

            <!-- Department Report (For regular admins - shows only their department) -->
            <?php if ($report_type === 'departments' && $_SESSION['role'] !== 'super_admin'): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle me-2"></i>
                    As a department admin, you can only view statistics for your own department. 
                    Use the Overview tab to see your department's summary statistics.
                </div>
            <?php endif; ?>

            <!-- Users Report -->
            <?php if ($report_type === 'users'): ?>
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-users me-2"></i>Top Active Users
                            <?php if ($_SESSION['role'] !== 'super_admin'): ?>
                                <small class="text-muted">- <?php echo htmlspecialchars($department_name); ?></small>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($data['top_users'])): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>User</th>
                                            <th>Department</th>
                                            <th>Files Uploaded</th>
                                            <th>Total Size</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data['top_users'] as $index => $user): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="me-3">
                                                            <span class="badge bg-secondary">#<?php echo $index + 1; ?></span>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                                            <br><small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($user['department_name']): ?>
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($user['department_name']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="badge bg-success"><?php echo number_format($user['files_uploaded']); ?></span></td>
                                                <td><?php echo formatFileSize($user['total_uploaded_size']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <div class="h5 text-muted">No active users found</div>
                                <small class="text-muted">No user activity recorded for the selected period in your department</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </section>
    
    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function toggleCustomDates(value) {
            const startDateCol = document.getElementById('start-date-col');
            const endDateCol = document.getElementById('end-date-col');
            
            if (value === 'custom') {
                startDateCol.style.display = 'block';
                endDateCol.style.display = 'block';
            } else {
                startDateCol.style.display = 'none';
                endDateCol.style.display = 'none';
            }
        }

        function exportReport() {
            alert('Export functionality would be implemented here');
            // Implementation for PDF/Excel export
        }

        // Initialize charts for overview report
        <?php if ($report_type === 'overview'): ?>
        // File Distribution Chart
        const fileCtx = document.getElementById('fileDistributionChart').getContext('2d');
        new Chart(fileCtx, {
            type: 'doughnut',
            data: {
                labels: ['Active Files', 'New Files', 'Deleted Files'],
                datasets: [{
                    data: [
                        <?php echo $data['stats']['files']['total_files'] - $data['stats']['files']['deleted_files']; ?>,
                        <?php echo $data['stats']['files']['new_files']; ?>,
                        <?php echo $data['stats']['files']['deleted_files']; ?>
                    ],
                    backgroundColor: ['#28a745', '#17a2b8', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        <?php if ($_SESSION['role'] === 'super_admin'): ?>
        // Department Activity Chart (for super admin)
        const deptCtx = document.getElementById('departmentActivityChart').getContext('2d');
        new Chart(deptCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo '"' . implode('","', array_column($data['departments'], 'department_code')) . '"'; ?>],
                datasets: [{
                    label: 'Files',
                    data: [<?php echo implode(',', array_column($data['departments'], 'file_count')); ?>],
                    backgroundColor: '#007bff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        <?php else: ?>
        // User Activity Chart (for department admin)
        const userCtx = document.getElementById('departmentActivityChart').getContext('2d');
        <?php 
        // Get top 5 users for chart
        $top_chart_users = array_slice($data['top_users'] ?? [], 0, 5);
        ?>
        new Chart(userCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo '"' . implode('","', array_map(function($u) { return $u['name'] . ' ' . $u['surname']; }, $top_chart_users)) . '"'; ?>],
                datasets: [{
                    label: 'Activities',
                    data: [<?php echo implode(',', array_column($top_chart_users, 'activities')); ?>],
                    backgroundColor: '#28a745'
                }, {
                    label: 'Files Uploaded',
                    data: [<?php echo implode(',', array_column($top_chart_users, 'files_uploaded')); ?>],
                    backgroundColor: '#007bff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        <?php endif; ?>
        <?php endif; ?>
    </script>
</body>
</html>