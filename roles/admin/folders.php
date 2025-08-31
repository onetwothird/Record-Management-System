<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Check if user is logged in and is admin
requireAdmin();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';
$folder_type_filter = isset($_GET['folder_type']) ? $_GET['folder_type'] : '';

// Build WHERE clause
$where_conditions = ["f.is_deleted = 0"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(f.folder_name LIKE ? OR f.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($department_filter)) {
    $where_conditions[] = "f.department_id = ?";
    $params[] = $department_filter;
}

if (!empty($folder_type_filter)) {
    $where_conditions[] = "f.folder_type = ?";
    $params[] = $folder_type_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM folders f 
                LEFT JOIN departments d ON f.department_id = d.id 
                LEFT JOIN users u ON f.created_by = u.id 
                WHERE $where_clause";

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_folders = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_folders / $limit);

// Get folders with details
$folders_query = "SELECT f.*, 
                         d.department_name, d.department_code,
                         u.username, u.name as creator_name, u.surname,
                         CONCAT(u.name, ' ', COALESCE(u.mi, ''), ' ', u.surname) as creator_full_name,
                         pf.folder_name as parent_folder_name
                  FROM folders f 
                  LEFT JOIN departments d ON f.department_id = d.id 
                  LEFT JOIN users u ON f.created_by = u.id 
                  LEFT JOIN folders pf ON f.parent_id = pf.id
                  WHERE $where_clause
                  ORDER BY f.created_at DESC 
                  LIMIT $limit OFFSET $offset";

$folders_stmt = $pdo->prepare($folders_query);
$folders_stmt->execute($params);
$folders = $folders_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get departments for filter
$dept_query = "SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name";
$dept_stmt = $pdo->prepare($dept_query);
$dept_stmt->execute();
$departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle folder actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $folder_id = $_POST['folder_id'] ?? 0;
    
    if ($action === 'delete' && $folder_id > 0) {
        // Check if folder has files or subfolders
        $check_query = "SELECT 
                           (SELECT COUNT(*) FROM files WHERE folder_id = ? AND is_deleted = 0) as file_count,
                           (SELECT COUNT(*) FROM folders WHERE parent_id = ? AND is_deleted = 0) as subfolder_count";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([$folder_id, $folder_id]);
        $counts = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($counts['file_count'] > 0 || $counts['subfolder_count'] > 0) {
            $error_message = "Cannot delete folder. It contains " . $counts['file_count'] . " files and " . $counts['subfolder_count'] . " subfolders.";
        } else {
            $delete_query = "UPDATE folders SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE id = ?";
            $delete_stmt = $pdo->prepare($delete_query);
            if ($delete_stmt->execute([$_SESSION['user_id'], $folder_id])) {
                $success_message = "Folder deleted successfully.";
            } else {
                $error_message = "Failed to delete folder.";
            }
        }
    }
    
    if ($action === 'toggle_public' && $folder_id > 0) {
        $toggle_query = "UPDATE folders SET is_public = NOT is_public WHERE id = ?";
        $toggle_stmt = $pdo->prepare($toggle_query);
        if ($toggle_stmt->execute([$folder_id])) {
            $success_message = "Folder visibility updated.";
        } else {
            $error_message = "Failed to update folder visibility.";
        }
    }
    
    if ($action === 'change_status' && $folder_id > 0) {
        $status = $_POST['status'] ?? 'active';
        $status_query = "UPDATE folders SET folder_status = ? WHERE id = ?";
        $status_stmt = $pdo->prepare($status_query);
        if ($status_stmt->execute([$status, $folder_id])) {
            $success_message = "Folder status updated.";
        } else {
            $error_message = "Failed to update folder status.";
        }
    }
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Folders - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?= time() ?>">
    <style>
        .folder-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            border-radius: 12px;
        }
        .folder-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .folder-icon {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .folder-actions {
            opacity: 0;
            transition: opacity 0.3s;
        }
        .folder-card:hover .folder-actions {
            opacity: 1;
        }
        .public-badge {
            background: linear-gradient(45deg, #28a745, #20c997);
        }
        .private-badge {
            background: linear-gradient(45deg, #6c757d, #495057);
        }
        .system-folder {
            border: 2px solid #007bff;
        }
        .department-folder {
            border: 2px solid #28a745;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'components/sidebar.html'; ?>
    <!-- Content -->
    <section id="content">
        <?php include 'components/navbar.html'; ?>
        <div class="container-fluid">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center bg-white p-3 rounded shadow-sm" style="margin-top: 15px;">
                        <h2 class="mb-0"><i class="fas fa-folder me-2 text-warning"></i>All Folders</h2>
                        <div class="text-muted">
                            Total: <?php echo number_format($total_folders); ?> folders
                        </div>
                    </div>
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

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search Folders</label>
                            <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by folder name or description...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="folder_type">
                                <option value="">All Types</option>
                                <option value="category" <?php echo $folder_type_filter == 'category' ? 'selected' : ''; ?>>Category</option>
                                <option value="custom" <?php echo $folder_type_filter == 'custom' ? 'selected' : ''; ?>>Custom</option>
                                <option value="system" <?php echo $folder_type_filter == 'system' ? 'selected' : ''; ?>>System</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- View Toggle -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="btn-group" role="group">
                    <input type="radio" class="btn-check" name="view" id="grid-view" autocomplete="off" checked>
                    <label class="btn btn-outline-secondary" for="grid-view">
                        <i class="fas fa-th-large"></i> Grid
                    </label>
                    <input type="radio" class="btn-check" name="view" id="list-view" autocomplete="off">
                    <label class="btn btn-outline-secondary" for="list-view">
                        <i class="fas fa-list"></i> List
                    </label>
                </div>
            </div>

            <!-- Grid View -->
            <div id="grid-container">
                <div class="row g-4">
                    <?php foreach ($folders as $folder): ?>
                        <div class="col-lg-3 col-md-4 col-sm-6">
                            <div class="card folder-card h-100 position-relative <?php echo $folder['is_system_folder'] ? 'system-folder' : ($folder['department_id'] ? 'department-folder' : ''); ?>">
                                <!-- Status Badge -->
                                <div class="status-badge">
                                    <?php if ($folder['folder_status'] === 'archived'): ?>
                                        <span class="badge bg-warning">Archived</span>
                                    <?php elseif ($folder['folder_status'] === 'hidden'): ?>
                                        <span class="badge bg-secondary">Hidden</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php endif; ?>
                                </div>

                                <div class="card-body text-center">
                                    <div class="folder-icon mx-auto" style="background-color: <?php echo $folder['folder_color']; ?>; color: white;">
                                        <i class="<?php echo $folder['folder_icon']; ?>"></i>
                                    </div>
                                    
                                    <h6 class="card-title mb-2"><?php echo htmlspecialchars($folder['folder_name']); ?></h6>
                                    
                                    <div class="mb-2">
                                        <span class="badge <?php echo $folder['is_public'] ? 'public-badge' : 'private-badge'; ?> text-white">
                                            <i class="fas <?php echo $folder['is_public'] ? 'fa-globe' : 'fa-lock'; ?>"></i>
                                            <?php echo $folder['is_public'] ? 'Public' : 'Private'; ?>
                                        </span>
                                    </div>

                                    <div class="row text-center mb-3">
                                        <div class="col-6">
                                            <div class="fw-bold text-primary"><?php echo number_format($folder['file_count']); ?></div>
                                            <small class="text-muted">Files</small>
                                        </div>
                                        <div class="col-6">
                                            <div class="fw-bold text-info"><?php echo formatFileSize($folder['folder_size']); ?></div>
                                            <small class="text-muted">Size</small>
                                        </div>
                                    </div>

                                    <?php if ($folder['department_name']): ?>
                                        <div class="mb-2">
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($folder['department_code']); ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <div class="text-muted small mb-3">
                                        <div>Created by: <?php echo htmlspecialchars($folder['creator_full_name']); ?></div>
                                        <div><?php echo date('M j, Y', strtotime($folder['created_at'])); ?></div>
                                    </div>

                                    <!-- Actions -->
                                    <div class="folder-actions">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="folder_details.php?id=<?php echo $folder['id']; ?>">
                                                        <i class="fas fa-eye me-2"></i>View Details
                                                    </a>
                                                </li>
                                                <li>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Toggle visibility?')">
                                                        <input type="hidden" name="action" value="toggle_public">
                                                        <input type="hidden" name="folder_id" value="<?php echo $folder['id']; ?>">
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="fas <?php echo $folder['is_public'] ? 'fa-lock' : 'fa-globe'; ?> me-2"></i>
                                                            Make <?php echo $folder['is_public'] ? 'Private' : 'Public'; ?>
                                                        </button>
                                                    </form>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li class="dropdown-submenu">
                                                    <a class="dropdown-item" href="#">
                                                        <i class="fas fa-cog me-2"></i>Change Status
                                                    </a>
                                                    <ul class="dropdown-menu">
                                                        <?php foreach (['active', 'archived', 'hidden'] as $status): ?>
                                                            <li>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="action" value="change_status">
                                                                    <input type="hidden" name="folder_id" value="<?php echo $folder['id']; ?>">
                                                                    <input type="hidden" name="status" value="<?php echo $status; ?>">
                                                                    <button type="submit" class="dropdown-item <?php echo $folder['folder_status'] === $status ? 'active' : ''; ?>">
                                                                        <?php echo ucfirst($status); ?>
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this folder?')">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="folder_id" value="<?php echo $folder['id']; ?>">
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="fas fa-trash me-2"></i>Delete
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($folders)): ?>
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                                <div class="h5 text-muted">No folders found</div>
                                <small class="text-muted">Try adjusting your search criteria</small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- List View -->
            <div id="list-container" style="display: none;">
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Folder</th>
                                        <th>Type</th>
                                        <th>Department</th>
                                        <th>Creator</th>
                                        <th>Files</th>
                                        <th>Size</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($folders as $folder): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="folder-icon me-3" style="background-color: <?php echo $folder['folder_color']; ?>; color: white; width: 40px; height: 40px; border-radius: 8px;">
                                                        <i class="<?php echo $folder['folder_icon']; ?>"></i>
                                                    </div>
                                                    <div>
                                                        <div class="fw-medium"><?php echo htmlspecialchars($folder['folder_name']); ?></div>
                                                        <span class="badge <?php echo $folder['is_public'] ? 'public-badge' : 'private-badge'; ?> text-white">
                                                            <i class="fas <?php echo $folder['is_public'] ? 'fa-globe' : 'fa-lock'; ?>"></i>
                                                            <?php echo $folder['is_public'] ? 'Public' : 'Private'; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo ucfirst($folder['folder_type']); ?></span>
                                                <?php if ($folder['is_system_folder']): ?>
                                                    <span class="badge bg-primary">System</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($folder['department_name']): ?>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($folder['department_code']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div>
                                                    <div class="fw-medium"><?php echo htmlspecialchars($folder['creator_full_name']); ?></div>
                                                    <small class="text-muted">@<?php echo htmlspecialchars($folder['username']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo number_format($folder['file_count']); ?></span>
                                            </td>
                                            <td><?php echo formatFileSize($folder['folder_size']); ?></td>
                                            <td>
                                                <?php if ($folder['folder_status'] === 'active'): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php elseif ($folder['folder_status'] === 'archived'): ?>
                                                    <span class="badge bg-warning">Archived</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Hidden</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div><?php echo date('M j, Y', strtotime($folder['created_at'])); ?></div>
                                                <small class="text-muted"><?php echo date('g:i A', strtotime($folder['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item" href="folder_details.php?id=<?php echo $folder['id']; ?>">
                                                                <i class="fas fa-eye me-2"></i>View Details
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this folder?')">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="folder_id" value="<?php echo $folder['id']; ?>">
                                                                <button type="submit" class="dropdown-item text-danger">
                                                                    <i class="fas fa-trash me-2"></i>Delete
                                                                </button>
                                                            </form>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php
                        $current_url = $_SERVER['REQUEST_URI'];
                        $url_parts = parse_url($current_url);
                        parse_str($url_parts['query'] ?? '', $query_params);
                        ?>
                        
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <?php $query_params['page'] = $page - 1; ?>
                                <a class="page-link" href="?<?php echo http_build_query($query_params); ?>">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php $query_params['page'] = $i; ?>
                                <a class="page-link" href="?<?php echo http_build_query($query_params); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <?php $query_params['page'] = $page + 1; ?>
                                <a class="page-link" href="?<?php echo http_build_query($query_params); ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </section>
    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // View toggle functionality
        document.getElementById('grid-view').addEventListener('change', function() {
            if (this.checked) {
                document.getElementById('grid-container').style.display = 'block';
                document.getElementById('list-container').style.display = 'none';
            }
        });

        document.getElementById('list-view').addEventListener('change', function() {
            if (this.checked) {
                document.getElementById('grid-container').style.display = 'none';
                document.getElementById('list-container').style.display = 'block';
            }
        });
    </script>
</body>
</html>