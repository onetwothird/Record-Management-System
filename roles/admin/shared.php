<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Check if user is logged in and is admin
requireAdmin();

// Get current page and search parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$share_type_filter = isset($_GET['share_type']) ? $_GET['share_type'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build WHERE clause
$where_conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(f.original_name LIKE ? OR u1.username LIKE ? OR u2.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($share_type_filter)) {
    $where_conditions[] = "fs.share_type = ?";
    $params[] = $share_type_filter;
}

if (!empty($status_filter)) {
    if ($status_filter === 'active') {
        $where_conditions[] = "fs.is_active = 1 AND (fs.expires_at IS NULL OR fs.expires_at > NOW())";
    } elseif ($status_filter === 'expired') {
        $where_conditions[] = "fs.expires_at IS NOT NULL AND fs.expires_at <= NOW()";
    } elseif ($status_filter === 'inactive') {
        $where_conditions[] = "fs.is_active = 0";
    }
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM file_shares fs 
                LEFT JOIN files f ON fs.file_id = f.id 
                LEFT JOIN users u1 ON fs.shared_by = u1.id 
                LEFT JOIN users u2 ON fs.shared_with = u2.id 
                WHERE $where_clause";

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_shares = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_shares / $limit);

// Get shared files with details
$shares_query = "SELECT fs.*, 
                        f.original_name, f.file_size, f.file_type, f.file_extension,
                        u1.username as shared_by_username, 
                        CONCAT(u1.name, ' ', COALESCE(u1.mi, ''), ' ', u1.surname) as shared_by_full_name,
                        u2.username as shared_with_username,
                        CONCAT(u2.name, ' ', COALESCE(u2.mi, ''), ' ', u2.surname) as shared_with_full_name,
                        d1.department_name as sharer_department,
                        d2.department_name as recipient_department
                 FROM file_shares fs 
                 LEFT JOIN files f ON fs.file_id = f.id 
                 LEFT JOIN users u1 ON fs.shared_by = u1.id 
                 LEFT JOIN users u2 ON fs.shared_with = u2.id
                 LEFT JOIN departments d1 ON u1.department_id = d1.id
                 LEFT JOIN departments d2 ON u2.department_id = d2.id
                 WHERE $where_clause
                 ORDER BY fs.created_at DESC 
                 LIMIT $limit OFFSET $offset";

$shares_stmt = $pdo->prepare($shares_query);
$shares_stmt->execute($params);
$shares = $shares_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle share actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $share_id = $_POST['share_id'] ?? 0;
    
    if ($action === 'revoke' && $share_id > 0) {
        $revoke_query = "UPDATE file_shares SET is_active = 0 WHERE id = ?";
        $revoke_stmt = $pdo->prepare($revoke_query);
        if ($revoke_stmt->execute([$share_id])) {
            $success_message = "Share access revoked successfully.";
        } else {
            $error_message = "Failed to revoke share access.";
        }
    }
    
    if ($action === 'activate' && $share_id > 0) {
        $activate_query = "UPDATE file_shares SET is_active = 1 WHERE id = ?";
        $activate_stmt = $pdo->prepare($activate_query);
        if ($activate_stmt->execute([$share_id])) {
            $success_message = "Share access activated successfully.";
        } else {
            $error_message = "Failed to activate share access.";
        }
    }
    
    if ($action === 'extend_expiry' && $share_id > 0) {
        $days = (int)($_POST['days'] ?? 30);
        $extend_query = "UPDATE file_shares SET expires_at = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id = ?";
        $extend_stmt = $pdo->prepare($extend_query);
        if ($extend_stmt->execute([$days, $share_id])) {
            $success_message = "Share expiry extended by $days days.";
        } else {
            $error_message = "Failed to extend share expiry.";
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

function getShareStatus($share) {
    if (!$share['is_active']) {
        return ['status' => 'inactive', 'class' => 'bg-secondary', 'text' => 'Inactive'];
    }
    
    if ($share['expires_at'] && strtotime($share['expires_at']) <= time()) {
        return ['status' => 'expired', 'class' => 'bg-danger', 'text' => 'Expired'];
    }
    
    if ($share['download_limit'] && $share['download_count'] >= $share['download_limit']) {
        return ['status' => 'limit_reached', 'class' => 'bg-warning', 'text' => 'Limit Reached'];
    }
    
    return ['status' => 'active', 'class' => 'bg-success', 'text' => 'Active'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared Files - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?= time() ?>">
    <style>
        .share-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
        }
        .share-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .file-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            margin-right: 15px;
        }
        .share-actions {
            opacity: 0;
            transition: opacity 0.3s;
        }
        .share-row:hover .share-actions {
            opacity: 1;
        }
        .public-share {
            border-left: 4px solid #28a745;
        }
        .user-share {
            border-left: 4px solid #007bff;
        }
        .department-share {
            border-left: 4px solid #ffc107;
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
                        <h2 class="mb-0"><i class="fas fa-share-alt me-2 text-info"></i>Shared Files</h2>
                        <div class="text-muted">
                            Total: <?php echo number_format($total_shares); ?> shares
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

            <!-- Stats Cards -->
            <?php
            $stats_query = "SELECT 
                            COUNT(*) as total_shares,
                            COUNT(CASE WHEN is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) THEN 1 END) as active_shares,
                            COUNT(CASE WHEN expires_at IS NOT NULL AND expires_at <= NOW() THEN 1 END) as expired_shares,
                            COUNT(CASE WHEN share_type = 'public' THEN 1 END) as public_shares,
                            SUM(download_count) as total_downloads
                            FROM file_shares";
            $stats_stmt = $pdo->prepare($stats_query);
            $stats_stmt->execute();
            $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
            ?>

            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="card text-white bg-primary">
                        <div class="card-body text-center">
                            <i class="fas fa-share-alt fa-2x mb-2"></i>
                            <h4><?php echo number_format($stats['total_shares']); ?></h4>
                            <small>Total Shares</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-white bg-success">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle fa-2x mb-2"></i>
                            <h4><?php echo number_format($stats['active_shares']); ?></h4>
                            <small>Active</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-white bg-danger">
                        <div class="card-body text-center">
                            <i class="fas fa-times-circle fa-2x mb-2"></i>
                            <h4><?php echo number_format($stats['expired_shares']); ?></h4>
                            <small>Expired</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body text-center">
                            <i class="fas fa-globe fa-2x mb-2"></i>
                            <h4><?php echo number_format($stats['public_shares']); ?></h4>
                            <small>Public Shares</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body text-center">
                            <i class="fas fa-download fa-2x mb-2"></i>
                            <h4><?php echo number_format($stats['total_downloads']); ?></h4>
                            <small>Total Downloads</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by filename or username...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Share Type</label>
                            <select class="form-select" name="share_type">
                                <option value="">All Types</option>
                                <option value="user" <?php echo $share_type_filter == 'user' ? 'selected' : ''; ?>>User</option>
                                <option value="public" <?php echo $share_type_filter == 'public' ? 'selected' : ''; ?>>Public</option>
                                <option value="department" <?php echo $share_type_filter == 'department' ? 'selected' : ''; ?>>Department</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="expired" <?php echo $status_filter == 'expired' ? 'selected' : ''; ?>>Expired</option>
                                <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
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

            <!-- Shared Files Table -->
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>File</th>
                                    <th>Share Type</th>
                                    <th>Shared By</th>
                                    <th>Shared With</th>
                                    <th>Downloads</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Expires</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($shares as $share): ?>
                                    <?php $status = getShareStatus($share); ?>
                                    <tr class="share-row <?php echo $share['share_type']; ?>-share">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="file-icon bg-primary text-white">
                                                    <?php
                                                    $ext = strtolower($share['file_extension'] ?? '');
                                                    $icon = 'fa-file';
                                                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) $icon = 'fa-image';
                                                    elseif (in_array($ext, ['pdf'])) $icon = 'fa-file-pdf';
                                                    elseif (in_array($ext, ['doc', 'docx'])) $icon = 'fa-file-word';
                                                    elseif (in_array($ext, ['xls', 'xlsx'])) $icon = 'fa-file-excel';
                                                    elseif (in_array($ext, ['ppt', 'pptx'])) $icon = 'fa-file-powerpoint';
                                                    elseif (in_array($ext, ['zip', 'rar', '7z'])) $icon = 'fa-file-archive';
                                                    ?>
                                                    <i class="fas <?php echo $icon; ?>"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-medium"><?php echo htmlspecialchars($share['original_name']); ?></div>
                                                    <small class="text-muted"><?php echo formatFileSize($share['file_size'] ?? 0); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $type_badges = [
                                                'user' => ['bg-primary', 'fa-user'],
                                                'public' => ['bg-success', 'fa-globe'],
                                                'department' => ['bg-warning', 'fa-building']
                                            ];
                                            $badge = $type_badges[$share['share_type']] ?? ['bg-secondary', 'fa-share'];
                                            ?>
                                            <span class="badge <?php echo $badge[0]; ?>">
                                                <i class="fas <?php echo $badge[1]; ?>"></i>
                                                <?php echo ucfirst($share['share_type']); ?>
                                            </span>
                                            <?php if ($share['password_protected']): ?>
                                                <span class="badge bg-dark ms-1">
                                                    <i class="fas fa-key"></i> Protected
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div>
                                                <div class="fw-medium"><?php echo htmlspecialchars($share['shared_by_full_name']); ?></div>
                                                <small class="text-muted">@<?php echo htmlspecialchars($share['shared_by_username']); ?></small>
                                                <?php if ($share['sharer_department']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($share['sharer_department']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($share['share_type'] === 'user' && $share['shared_with_full_name']): ?>
                                                <div>
                                                    <div class="fw-medium"><?php echo htmlspecialchars($share['shared_with_full_name']); ?></div>
                                                    <small class="text-muted">@<?php echo htmlspecialchars($share['shared_with_username']); ?></small>
                                                    <?php if ($share['recipient_department']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($share['recipient_department']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php elseif ($share['share_type'] === 'public'): ?>
                                                <span class="text-muted">
                                                    <i class="fas fa-globe"></i> Anyone with link
                                                </span>
                                            <?php elseif ($share['share_type'] === 'department'): ?>
                                                <span class="text-muted">
                                                    <i class="fas fa-building"></i> Department members
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="badge bg-info me-2"><?php echo number_format($share['download_count']); ?></span>
                                                <?php if ($share['download_limit']): ?>
                                                    <small class="text-muted">/ <?php echo number_format($share['download_limit']); ?></small>
                                                    <div class="progress ms-2" style="width: 50px; height: 6px;">
                                                        <div class="progress-bar" style="width: <?php echo min(100, ($share['download_count'] / $share['download_limit']) * 100); ?>%"></div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $status['class']; ?>"><?php echo $status['text']; ?></span>
                                        </td>
                                        <td>
                                            <div><?php echo date('M j, Y', strtotime($share['created_at'])); ?></div>
                                            <small class="text-muted"><?php echo date('g:i A', strtotime($share['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($share['expires_at']): ?>
                                                <div><?php echo date('M j, Y', strtotime($share['expires_at'])); ?></div>
                                                <small class="text-muted"><?php echo date('g:i A', strtotime($share['expires_at'])); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Never</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="share-actions">
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item" href="<?php echo 'shared_link.php?token=' . $share['share_token']; ?>" target="_blank">
                                                                <i class="fas fa-external-link-alt me-2"></i>View Share Link
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        
                                                        <?php if ($share['is_active']): ?>
                                                            <li>
                                                                <form method="POST" class="d-inline" onsubmit="return confirm('Revoke share access?')">
                                                                    <input type="hidden" name="action" value="revoke">
                                                                    <input type="hidden" name="share_id" value="<?php echo $share['id']; ?>">
                                                                    <button type="submit" class="dropdown-item text-warning">
                                                                        <i class="fas fa-ban me-2"></i>Revoke Access
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        <?php else: ?>
                                                            <li>
                                                                <form method="POST" class="d-inline" onsubmit="return confirm('Activate share access?')">
                                                                    <input type="hidden" name="action" value="activate">
                                                                    <input type="hidden" name="share_id" value="<?php echo $share['id']; ?>">
                                                                    <button type="submit" class="dropdown-item text-success">
                                                                        <i class="fas fa-check me-2"></i>Activate
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        <?php endif; ?>
                                                        
                                                        <li>
                                                            <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#extendModal<?php echo $share['id']; ?>">
                                                                <i class="fas fa-calendar-plus me-2"></i>Extend Expiry
                                                            </button>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>

                                            <!-- Extend Expiry Modal -->
                                            <div class="modal fade" id="extendModal<?php echo $share['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-sm">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Extend Expiry</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST">
                                                            <div class="modal-body">
                                                                <input type="hidden" name="action" value="extend_expiry">
                                                                <input type="hidden" name="share_id" value="<?php echo $share['id']; ?>">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Extend by (days)</label>
                                                                    <select class="form-select" name="days">
                                                                        <option value="7">7 days</option>
                                                                        <option value="30" selected>30 days</option>
                                                                        <option value="90">90 days</option>
                                                                        <option value="365">1 year</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-primary">Extend</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($shares)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-5">
                                            <i class="fas fa-share-alt fa-3x text-muted mb-3"></i>
                                            <div class="h5 text-muted">No shared files found</div>
                                            <small class="text-muted">Try adjusting your search criteria</small>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
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
</body>
</html>