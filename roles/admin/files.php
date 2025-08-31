<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Check if user is logged in and is admin
requireAdmin();

// Get current admin's department and role for hierarchical access
$admin_id = $_SESSION['user_id'];
$admin_role = $_SESSION['role'];

// Determine admin's scope - Super admins see everything, regular admins see their department + subordinates
$admin_scope_query = "SELECT department_id, role FROM users WHERE id = ?";
$admin_scope_stmt = $pdo->prepare($admin_scope_query);
$admin_scope_stmt->execute([$admin_id]);
$admin_info = $admin_scope_stmt->fetch(PDO::FETCH_ASSOC);

// Get current page and search parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';
$file_type_filter = isset($_GET['file_type']) ? $_GET['file_type'] : '';
$user_filter = isset($_GET['user_filter']) ? $_GET['user_filter'] : '';
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';

// Build WHERE clause based on admin privileges
$where_conditions = ["f.is_deleted = 0"];
$params = [];

// Admin scope filtering
if ($admin_role !== 'super_admin') {
    // Regular admins only see files from their department and subordinate departments
    $where_conditions[] = "(u.department_id = ? OR u.department_id IN (
        SELECT id FROM departments WHERE head_of_department = (
            SELECT CONCAT(name, ' ', surname) FROM users WHERE id = ?
        )
    ))";
    $params[] = $admin_info['department_id'];
    $params[] = $admin_id;
}

// Search filtering
if (!empty($search)) {
    $where_conditions[] = "(f.original_name LIKE ? OR f.description LIKE ? OR u.name LIKE ? OR u.surname LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Department filtering
if (!empty($department_filter)) {
    $where_conditions[] = "u.department_id = ?";
    $params[] = $department_filter;
}

// File type filtering
if (!empty($file_type_filter)) {
    $where_conditions[] = "f.file_type = ?";
    $params[] = $file_type_filter;
}

// User filtering
if (!empty($user_filter)) {
    $where_conditions[] = "f.uploaded_by = ?";
    $params[] = $user_filter;
}

// Date filtering
if (!empty($date_filter)) {
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = "DATE(f.uploaded_at) = CURDATE()";
            break;
        case 'week':
            $where_conditions[] = "f.uploaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where_conditions[] = "f.uploaded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case 'quarter':
            $where_conditions[] = "f.uploaded_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            break;
    }
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM files f 
                LEFT JOIN folders fo ON f.folder_id = fo.id 
                LEFT JOIN users u ON f.uploaded_by = u.id 
                LEFT JOIN departments d ON u.department_id = d.id 
                WHERE $where_clause";

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_files = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_files / $limit);

// Get files with enhanced details
$files_query = "SELECT f.*, fo.folder_name, u.username, u.name as user_name, u.surname, u.employee_id,
                       u.position, u.profile_image, u.last_login,
                       d.department_name, d.department_code, d.head_of_department,
                       CONCAT(u.name, ' ', COALESCE(u.mi, ''), ' ', u.surname) as uploader_full_name,
                       (SELECT COUNT(*) FROM file_comments fc WHERE fc.file_id = f.id AND fc.is_deleted = 0) as comment_count,
                       (SELECT COUNT(*) FROM activity_logs al WHERE al.resource_type = 'file' AND al.resource_id = f.id) as activity_count,
                       f.academic_year, f.semester
                FROM files f 
                LEFT JOIN folders fo ON f.folder_id = fo.id 
                LEFT JOIN users u ON f.uploaded_by = u.id 
                LEFT JOIN departments d ON u.department_id = d.id 
                WHERE $where_clause
                ORDER BY f.uploaded_at DESC 
                LIMIT $limit OFFSET $offset";

$files_stmt = $pdo->prepare($files_query);
$files_stmt->execute($params);
$files = $files_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get departments for filter (based on admin scope)
if ($admin_role === 'super_admin') {
    $dept_query = "SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name";
    $dept_stmt = $pdo->prepare($dept_query);
    $dept_stmt->execute();
} else {
    $dept_query = "SELECT * FROM departments WHERE is_active = 1 AND (id = ? OR head_of_department = (
                   SELECT CONCAT(name, ' ', surname) FROM users WHERE id = ?
                   )) ORDER BY department_name";
    $dept_stmt = $pdo->prepare($dept_query);
    $dept_stmt->execute([$admin_info['department_id'], $admin_id]);
}
$departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get file types for filter
$types_query = "SELECT DISTINCT f.file_type FROM files f 
                LEFT JOIN users u ON f.uploaded_by = u.id
                WHERE f.is_deleted = 0 AND f.file_type IS NOT NULL";
if ($admin_role !== 'super_admin') {
    $types_query .= " AND (u.department_id = ? OR u.department_id IN (
                      SELECT id FROM departments WHERE head_of_department = (
                          SELECT CONCAT(name, ' ', surname) FROM users WHERE id = ?
                      )))";
}
$types_query .= " ORDER BY f.file_type";

$types_stmt = $pdo->prepare($types_query);
if ($admin_role !== 'super_admin') {
    $types_stmt->execute([$admin_info['department_id'], $admin_id]);
} else {
    $types_stmt->execute();
}
$file_types = $types_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get users for filter (within admin scope)
$users_query = "SELECT u.id, u.username, u.name, u.surname, u.employee_id, u.position, 
                       CONCAT(u.name, ' ', COALESCE(u.mi, ''), ' ', u.surname) as full_name,
                       d.department_code
                FROM users u 
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE u.is_approved = 1";
if ($admin_role !== 'super_admin') {
    $users_query .= " AND (u.department_id = ? OR u.department_id IN (
                      SELECT id FROM departments WHERE head_of_department = (
                          SELECT CONCAT(name, ' ', surname) FROM users WHERE id = ?
                      )))";
}
$users_query .= " ORDER BY u.name, u.surname";

$users_stmt = $pdo->prepare($users_query);
if ($admin_role !== 'super_admin') {
    $users_stmt->execute([$admin_info['department_id'], $admin_id]);
} else {
    $users_stmt->execute();
}
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle file actions with enhanced permissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $file_id = $_POST['file_id'] ?? 0;
    
    // Verify admin has permission to perform action on this file
    $permission_check = "SELECT f.*, u.department_id as uploader_dept FROM files f 
                        JOIN users u ON f.uploaded_by = u.id WHERE f.id = ?";
    $perm_stmt = $pdo->prepare($permission_check);
    $perm_stmt->execute([$file_id]);
    $file_to_modify = $perm_stmt->fetch(PDO::FETCH_ASSOC);
    
    $has_permission = false;
    if ($admin_role === 'super_admin') {
        $has_permission = true;
    } elseif ($file_to_modify && ($file_to_modify['uploader_dept'] == $admin_info['department_id'])) {
        $has_permission = true;
    }
    
    if ($has_permission && $file_to_modify) {
        switch ($action) {
            case 'delete':
                $delete_query = "UPDATE files SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE id = ?";
                $delete_stmt = $pdo->prepare($delete_query);
                if ($delete_stmt->execute([$_SESSION['user_id'], $file_id])) {
                    // Log activity
                    $log_query = "INSERT INTO activity_logs (user_id, action, resource_type, resource_id, description, ip_address, user_agent) 
                                 VALUES (?, 'delete', 'file', ?, ?, ?, ?)";
                    $log_stmt = $pdo->prepare($log_query);
                    $log_stmt->execute([
                        $_SESSION['user_id'], 
                        $file_id, 
                        "Admin deleted file: " . $file_to_modify['original_name'],
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                    $success_message = "File deleted successfully.";
                } else {
                    $error_message = "Failed to delete file.";
                }
                break;
                
            case 'toggle_public':
                $toggle_query = "UPDATE files SET is_public = NOT is_public WHERE id = ?";
                $toggle_stmt = $pdo->prepare($toggle_query);
                if ($toggle_stmt->execute([$file_id])) {
                    // Log activity
                    $visibility = $file_to_modify['is_public'] ? 'private' : 'public';
                    $log_query = "INSERT INTO activity_logs (user_id, action, resource_type, resource_id, description, ip_address, user_agent) 
                                 VALUES (?, 'visibility_change', 'file', ?, ?, ?, ?)";
                    $log_stmt = $pdo->prepare($log_query);
                    $log_stmt->execute([
                        $_SESSION['user_id'], 
                        $file_id, 
                        "Admin changed file visibility to $visibility: " . $file_to_modify['original_name'],
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                    $success_message = "File visibility updated.";
                } else {
                    $error_message = "Failed to update file visibility.";
                }
                break;
                
            case 'move_folder':
                $new_folder_id = $_POST['new_folder_id'] ?? 0;
                $move_query = "UPDATE files SET folder_id = ? WHERE id = ?";
                $move_stmt = $pdo->prepare($move_query);
                if ($move_stmt->execute([$new_folder_id, $file_id])) {
                    $success_message = "File moved successfully.";
                } else {
                    $error_message = "Failed to move file.";
                }
                break;
        }
    } else {
        $error_message = "You don't have permission to perform this action.";
    }
}

// Get admin statistics
$stats_query = "SELECT 
    COUNT(*) as total_files,
    SUM(CASE WHEN DATE(f.uploaded_at) = CURDATE() THEN 1 ELSE 0 END) as today_uploads,
    SUM(CASE WHEN f.uploaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as week_uploads,
    SUM(f.file_size) as total_size,
    COUNT(DISTINCT f.uploaded_by) as unique_uploaders
    FROM files f 
    LEFT JOIN users u ON f.uploaded_by = u.id 
    WHERE f.is_deleted = 0";

if ($admin_role !== 'super_admin') {
    $stats_query .= " AND (u.department_id = ? OR u.department_id IN (
                      SELECT id FROM departments WHERE head_of_department = (
                          SELECT CONCAT(name, ' ', surname) FROM users WHERE id = ?
                      )))";
}

$stats_stmt = $pdo->prepare($stats_query);
if ($admin_role !== 'super_admin') {
    $stats_stmt->execute([$admin_info['department_id'], $admin_id]);
} else {
    $stats_stmt->execute();
}
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

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

function getFileIconClass($extension) {
    $ext = strtolower($extension ?? '');
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'svg'])) {
        return 'img';
    } elseif (in_array($ext, ['pdf'])) {
        return 'pdf';
    } elseif (in_array($ext, ['doc', 'docx'])) {
        return 'doc';
    } elseif (in_array($ext, ['xls', 'xlsx', 'csv'])) {
        return 'xls';
    } elseif (in_array($ext, ['ppt', 'pptx'])) {
        return 'ppt';
    } elseif (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'])) {
        return 'zip';
    } else {
        return 'default';
    }
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    
    return date('M j, Y', strtotime($datetime));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Admin Files - Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?= time() ?>">
    <style>
        .enhanced-files-container {
            padding: 20px;
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - 76px);
        }
        
        .enhanced-files-container.sidebar-collapsed {
            margin-left: 80px;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 1.5rem;
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: translate(30px, -30px);
        }
        
        .stat-card.warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-card.success { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-card.info { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .stat-card.dark { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.875rem;
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .stat-icon {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 2.5rem;
            opacity: 0.3;
        }
        
        .enhanced-filter-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 2rem;
        }
        
        .enhanced-files-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            border: none;
        }
        
        .enhanced-files-table .table {
            margin-bottom: 0;
        }
        
        .enhanced-files-table .table th {
            background: #f8f9ff;
            border-bottom: 1px solid #e3e6f0;
            font-weight: 600;
            color: #5a5c69;
            padding: 1rem;
        }
        
        .enhanced-files-table .table td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .file-icon-enhanced {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            flex-shrink: 0;
        }
        
        .file-icon-enhanced.pdf { background: linear-gradient(135deg, #ff6b6b, #ee5a52); }
        .file-icon-enhanced.doc { background: linear-gradient(135deg, #4fc3f7, #29b6f6); }
        .file-icon-enhanced.xls { background: linear-gradient(135deg, #66bb6a, #4caf50); }
        .file-icon-enhanced.img { background: linear-gradient(135deg, #ab47bc, #9c27b0); }
        .file-icon-enhanced.default { background: linear-gradient(135deg, #78909c, #607d8b); }
        
        .file-details h6 {
            margin: 0 0 0.25rem 0;
            color: #2d3436;
            font-weight: 600;
        }
        
        .file-meta {
            font-size: 0.875rem;
            color: #636e72;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .user-details h6 {
            margin: 0 0 0.125rem 0;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .user-details small {
            color: #636e72;
            font-size: 0.75rem;
        }
        
        .badge-enhanced {
            padding: 0.5rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-public { background: #d4edda; color: #155724; }
        .badge-private { background: #f8d7da; color: #721c24; }
        .badge-department { background: #e2e3f1; color: #383d41; }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .action-btn:hover { transform: translateY(-2px); }
        .action-btn.download { background: #e3f2fd; color: #1976d2; }
        .action-btn.toggle { background: #fff3e0; color: #f57c00; }
        .action-btn.delete { background: #ffebee; color: #d32f2f; }
        .action-btn.download:hover { background: #bbdefb; }
        .action-btn.toggle:hover { background: #ffe0b2; }
        .action-btn.delete:hover { background: #ffcdd2; }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #636e72;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
        
        @media (max-width: 768px) {
            .enhanced-files-container {
                margin-left: 0;
                padding: 15px;
            }
            
            .stats-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="bg-light">
    <!-- Sidebar Component -->
    <?php include 'components/sidebar.html'; ?>

    <!-- Content -->
    <section id="content">
        <!-- Navbar Component -->
        <?php include 'components/navbar.html'; ?>
    
        <div class="enhanced-files-container" id="files-container">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">
                        <i class="fas fa-files me-2"></i>Enhanced File Management
                    </h2>
                    <p class="text-muted mb-0">
                        <?php echo $admin_role === 'super_admin' ? 'System-wide file overview' : 'Department files under your supervision'; ?>
                    </p>
                </div>
                <div class="badge badge-enhanced badge-department fs-6">
                    <?php echo number_format($total_files); ?> total files
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['total_files']); ?></div>
                    <div class="stat-label">Total Files</div>
                    <i class="fas fa-file-alt stat-icon"></i>
                </div>
                <div class="stat-card success">
                    <div class="stat-value"><?php echo number_format($stats['today_uploads']); ?></div>
                    <div class="stat-label">Uploaded Today</div>
                    <i class="fas fa-upload stat-icon"></i>
                </div>
                <div class="stat-card warning">
                    <div class="stat-value"><?php echo number_format($stats['week_uploads']); ?></div>
                    <div class="stat-label">This Week</div>
                    <i class="fas fa-calendar-week stat-icon"></i>
                </div>
                <div class="stat-card info">
                    <div class="stat-value"><?php echo formatFileSize($stats['total_size'] ?? 0); ?></div>
                    <div class="stat-label">Total Storage</div>
                    <i class="fas fa-hdd stat-icon"></i>
                </div>
                <div class="stat-card dark">
                    <div class="stat-value"><?php echo number_format($stats['unique_uploaders']); ?></div>
                    <div class="stat-label">Active Users</div>
                    <i class="fas fa-users stat-icon"></i>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Enhanced Filters -->
            <div class="card enhanced-filter-card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label fw-semibold">Search Files</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" name="search" 
                                    value="<?php echo htmlspecialchars($search); ?>" 
                                    placeholder="Search files, users...">
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label fw-semibold">Department</label>
                            <select class="form-select" name="department">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" 
                                            <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department_code']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label fw-semibold">User</label>
                            <select class="form-select" name="user_filter">
                                <option value="">All Users</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" 
                                            <?php echo $user_filter == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label fw-semibold">File Type</label>
                            <select class="form-select" name="file_type">
                                <option value="">All Types</option>
                                <?php foreach ($file_types as $type): ?>
                                    <option value="<?php echo $type['file_type']; ?>" 
                                            <?php echo $file_type_filter == $type['file_type'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['file_type']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-6">
                            <label class="form-label fw-semibold">Date Range</label>
                            <select class="form-select" name="date_filter">
                                <option value="">All Time</option>
                                <option value="today" <?php echo $date_filter == 'today' ? 'selected' : ''; ?>>Today</option>
                                <option value="week" <?php echo $date_filter == 'week' ? 'selected' : ''; ?>>This Week</option>
                                <option value="month" <?php echo $date_filter == 'month' ? 'selected' : ''; ?>>This Month</option>
                                <option value="quarter" <?php echo $date_filter == 'quarter' ? 'selected' : ''; ?>>This Quarter</option>
                            </select>
                        </div>
                        <div class="col-lg-1 col-md-6 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-1"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Enhanced Files Table -->
            <div class="card enhanced-files-table">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 35%;">File Details</th>
                                <th style="width: 20%;">Uploader</th>
                                <th style="width: 15%;">Department</th>
                                <th style="width: 10%;">Size</th>
                                <th style="width: 10%;">Status</th>
                                <th style="width: 10%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($files as $file): ?>
                                <tr>
                                    <td>
                                        <div class="file-item">
                                            <div class="file-icon-enhanced <?php echo getFileIconClass($file['file_extension'] ?? ''); ?>">
                                                <?php
                                                $icon = 'fa-file';
                                                switch(getFileIconClass($file['file_extension'] ?? '')) {
                                                    case 'pdf': $icon = 'fa-file-pdf'; break;
                                                    case 'doc': $icon = 'fa-file-word'; break;
                                                    case 'xls': $icon = 'fa-file-excel'; break;
                                                    case 'ppt': $icon = 'fa-file-powerpoint'; break;
                                                    case 'img': $icon = 'fa-file-image'; break;
                                                    case 'zip': $icon = 'fa-file-archive'; break;
                                                    default: $icon = 'fa-file';
                                                }
                                                ?>
                                                <i class="fas <?php echo $icon; ?>"></i>
                                            </div>
                                            <div class="file-details flex-grow-1">
                                                <h6><?php echo htmlspecialchars($file['original_name']); ?></h6>
                                                <div class="file-meta">
                                                    <span class="me-3">
                                                        <i class="fas fa-folder me-1"></i>
                                                        <?php echo htmlspecialchars($file['folder_name'] ?? 'No Folder'); ?>
                                                    </span>
                                                    <span class="me-3">
                                                        <i class="fas fa-download me-1"></i>
                                                        <?php echo number_format($file['download_count'] ?? 0); ?>
                                                    </span>
                                                    <span class="me-3">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php echo timeAgo($file['uploaded_at']); ?>
                                                    </span>
                                                    <?php if ($file['comment_count'] > 0): ?>
                                                    <span class="me-3">
                                                        <i class="fas fa-comments me-1"></i>
                                                        <?php echo $file['comment_count']; ?>
                                                    </span>
                                                    <?php endif; ?>
                                                    <?php if ($file['academic_year']): ?>
                                                    <span class="badge bg-light text-dark me-2">
                                                        AY <?php echo $file['academic_year']; ?> - <?php echo ucfirst($file['semester']); ?>
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="user-info">
                                            <?php if ($file['profile_image']): ?>
                                                <img src="<?php echo htmlspecialchars($file['profile_image']); ?>" 
                                                    alt="Profile" class="user-avatar">
                                            <?php else: ?>
                                                <div class="user-avatar">
                                                    <?php echo strtoupper(substr($file['user_name'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="user-details">
                                                <h6><?php echo htmlspecialchars($file['uploader_full_name']); ?></h6>
                                                <small>
                                                    @<?php echo htmlspecialchars($file['username']); ?>
                                                    <?php if ($file['employee_id']): ?>
                                                        • <?php echo htmlspecialchars($file['employee_id']); ?>
                                                    <?php endif; ?>
                                                </small>
                                                <?php if ($file['position']): ?>
                                                    <small class="d-block text-muted">
                                                        <?php echo htmlspecialchars($file['position']); ?>
                                                    </small>
                                                <?php endif; ?>
                                                <?php if ($file['last_login']): ?>
                                                    <small class="d-block text-success">
                                                        <i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i>
                                                        Last seen <?php echo timeAgo($file['last_login']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($file['department_name']): ?>
                                            <div class="text-center">
                                                <span class="badge badge-enhanced badge-department">
                                                    <?php echo htmlspecialchars($file['department_code']); ?>
                                                </span>
                                                <small class="d-block text-muted mt-1">
                                                    <?php echo htmlspecialchars($file['department_name']); ?>
                                                </small>
                                                <?php if ($file['head_of_department']): ?>
                                                    <small class="d-block text-muted">
                                                        Head: <?php echo htmlspecialchars($file['head_of_department']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">No Department</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="fw-bold"><?php echo formatFileSize($file['file_size'] ?? 0); ?></div>
                                        <small class="text-muted">
                                            <?php echo strtoupper($file['file_extension'] ?? 'FILE'); ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-enhanced <?php echo $file['is_public'] ? 'badge-public' : 'badge-private'; ?>">
                                            <i class="fas <?php echo $file['is_public'] ? 'fa-globe' : 'fa-lock'; ?> me-1"></i>
                                            <?php echo $file['is_public'] ? 'Public' : 'Private'; ?>
                                        </span>
                                        <?php if ($file['activity_count'] > 5): ?>
                                            <small class="d-block text-success mt-1">
                                                <i class="fas fa-fire me-1"></i> Active
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="download.php?id=<?php echo $file['id']; ?>" 
                                            class="action-btn download" title="Download File">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            
                                            <form method="POST" class="d-inline" 
                                                onsubmit="return confirm('Toggle visibility for this file?')">
                                                <input type="hidden" name="action" value="toggle_public">
                                                <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                                <button type="submit" class="action-btn toggle" 
                                                        title="Toggle Visibility">
                                                    <i class="fas <?php echo $file['is_public'] ? 'fa-lock' : 'fa-globe'; ?>"></i>
                                                </button>
                                            </form>
                                            
                                            <form method="POST" class="d-inline" 
                                                onsubmit="return confirm('Are you sure you want to delete this file? This action cannot be undone.')">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                                <button type="submit" class="action-btn delete" title="Delete File">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($files)): ?>
                                <tr>
                                    <td colspan="6" class="text-center empty-state">
                                        <i class="fas fa-file-alt"></i>
                                        <h5 class="mt-3">No Files Found</h5>
                                        <p class="text-muted">
                                            <?php if (!empty($search) || !empty($department_filter) || !empty($user_filter)): ?>
                                                No files match your current filters. Try adjusting your search criteria.
                                            <?php else: ?>
                                                No files have been uploaded yet in your administrative scope.
                                            <?php endif; ?>
                                        </p>
                                        <?php if (!empty($search) || !empty($department_filter) || !empty($user_filter)): ?>
                                            <a href="?" class="btn btn-outline-primary">
                                                <i class="fas fa-times me-2"></i>Clear Filters
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Enhanced Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div class="text-muted">
                        Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $total_files); ?> 
                        of <?php echo number_format($total_files); ?> files
                    </div>
                    <nav>
                        <ul class="pagination mb-0">
                            <?php
                            $current_url = $_SERVER['REQUEST_URI'];
                            $url_parts = parse_url($current_url);
                            parse_str($url_parts['query'] ?? '', $query_params);
                            ?>
                            
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <?php $query_params['page'] = $page - 1; ?>
                                    <a class="page-link" href="?<?php echo http_build_query($query_params); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page > 1): ?>
                                <li class="page-item">
                                    <?php $query_params['page'] = 1; ?>
                                    <a class="page-link" href="?<?php echo http_build_query($query_params); ?>">1</a>
                                </li>
                                <?php if ($start_page > 2): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php $query_params['page'] = $i; ?>
                                    <a class="page-link" href="?<?php echo http_build_query($query_params); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <?php $query_params['page'] = $total_pages; ?>
                                    <a class="page-link" href="?<?php echo http_build_query($query_params); ?>">
                                        <?php echo $total_pages; ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <?php $query_params['page'] = $page + 1; ?>
                                    <a class="page-link" href="?<?php echo http_build_query($query_params); ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
            
            <!-- Quick Actions Panel -->
            <div class="row mt-4">
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">
                                <i class="fas fa-chart-bar me-2"></i>File Statistics
                            </h6>
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="fw-bold text-primary"><?php echo number_format($stats['total_files']); ?></div>
                                    <small class="text-muted">Total</small>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold text-success"><?php echo number_format($stats['today_uploads']); ?></div>
                                    <small class="text-muted">Today</small>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold text-info"><?php echo number_format($stats['unique_uploaders']); ?></div>
                                    <small class="text-muted">Users</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">
                                <i class="fas fa-shield-alt me-2"></i>Admin Scope
                            </h6>
                            <p class="text-muted mb-2">
                                <?php echo $admin_role === 'super_admin' ? 'System Administrator' : 'Department Administrator'; ?>
                            </p>
                            <small class="text-muted">
                                <?php if ($admin_role === 'super_admin'): ?>
                                    You have full system access to all files and departments.
                                <?php else: ?>
                                    You can manage files from your department and supervised departments.
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">
                                <i class="fas fa-tools me-2"></i>Quick Actions
                            </h6>
                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-primary btn-sm" onclick="exportFileReport()">
                                    <i class="fas fa-file-export me-1"></i>Export Report
                                </button>
                                <button class="btn btn-outline-secondary btn-sm" onclick="bulkActions()">
                                    <i class="fas fa-tasks me-1"></i>Bulk Actions
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <script src="assets/js/script.js?v=<?= time() ?>"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle sidebar state
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('#sidebar');
            const filesContainer = document.querySelector('#files-container');
            
            function updateLayout() {
                if (sidebar && sidebar.classList.contains('collapsed')) {
                    filesContainer.classList.add('sidebar-collapsed');
                } else {
                    filesContainer.classList.remove('sidebar-collapsed');
                }
            }
            
            updateLayout();
            
            // Watch for sidebar changes
            if (sidebar) {
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.attributeName === 'class') {
                            updateLayout();
                        }
                    });
                });
                observer.observe(sidebar, { attributes: true });
            }
        });
        
        // Enhanced functionality
        function exportFileReport() {
            // This would typically send an AJAX request to generate a report
            alert('Export functionality would be implemented here');
        }
        
        function bulkActions() {
            // This would open a modal for bulk actions
            alert('Bulk actions functionality would be implemented here');
        }
        
        // Auto-refresh for real-time updates (optional)
        function setupAutoRefresh() {
            setInterval(function() {
                // Check for new files or updates
                fetch('?ajax=1&check_updates=1')
                    .then(response => response.json())
                    .then(data => {
                        if (data.has_updates) {
                            // Show notification or update UI
                            const notification = document.createElement('div');
                            notification.className = 'alert alert-info alert-dismissible fade show position-fixed top-0 end-0 m-3';
                            notification.style.zIndex = '9999';
                            notification.innerHTML = `
                                <i class="fas fa-info-circle me-2"></i>New files detected. 
                                <a href="#" onclick="location.reload()" class="alert-link">Refresh page</a>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            `;
                            document.body.appendChild(notification);
                        }
                    })
                    .catch(error => console.log('Auto-refresh check failed:', error));
            }, 60000); // Check every minute
        }
        
        // Uncomment to enable auto-refresh
        // setupAutoRefresh();
    </script>
</body>
</html>