<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Check if user is logged in and is admin
requireAdmin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Update system settings
    if ($action === 'update_system_settings') {
        $settings = [
            'site_name' => $_POST['site_name'] ?? '',
            'site_description' => $_POST['site_description'] ?? '',
            'admin_email' => $_POST['admin_email'] ?? '',
            'max_file_size' => (int)($_POST['max_file_size'] ?? 100),
            'allowed_file_types' => $_POST['allowed_file_types'] ?? '',
            'enable_public_registration' => isset($_POST['enable_public_registration']) ? 1 : 0,
            'require_admin_approval' => isset($_POST['require_admin_approval']) ? 1 : 0,
            'enable_email_notifications' => isset($_POST['enable_email_notifications']) ? 1 : 0,
            'session_timeout' => (int)($_POST['session_timeout'] ?? 30),
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0
        ];
        
        foreach ($settings as $key => $value) {
            $type = is_int($value) ? 'integer' : 'string';
            $query = "INSERT INTO system_settings (setting_key, setting_value, setting_type, updated_by) 
                      VALUES (?, ?, ?, ?) 
                      ON DUPLICATE KEY UPDATE 
                      setting_value = VALUES(setting_value), 
                      setting_type = VALUES(setting_type),
                      updated_by = VALUES(updated_by),
                      updated_at = CURRENT_TIMESTAMP";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$key, $value, $type, $_SESSION['user_id']]);
        }
        
        $success_message = "System settings updated successfully.";
    }
    
    // Update security settings
    if ($action === 'update_security_settings') {
        $settings = [
            'password_min_length' => (int)($_POST['password_min_length'] ?? 8),
            'password_require_special' => isset($_POST['password_require_special']) ? 1 : 0,
            'max_login_attempts' => (int)($_POST['max_login_attempts'] ?? 3),
            'lockout_duration' => (int)($_POST['lockout_duration'] ?? 15),
            'enable_two_factor' => isset($_POST['enable_two_factor']) ? 1 : 0,
            'force_https' => isset($_POST['force_https']) ? 1 : 0,
            'session_security' => isset($_POST['session_security']) ? 1 : 0
        ];
        
        foreach ($settings as $key => $value) {
            $type = 'integer';
            $query = "INSERT INTO system_settings (setting_key, setting_value, setting_type, updated_by) 
                      VALUES (?, ?, ?, ?) 
                      ON DUPLICATE KEY UPDATE 
                      setting_value = VALUES(setting_value), 
                      updated_by = VALUES(updated_by),
                      updated_at = CURRENT_TIMESTAMP";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$key, $value, $type, $_SESSION['user_id']]);
        }
        
        $success_message = "Security settings updated successfully.";
    }
    
    // Update storage settings
    if ($action === 'update_storage_settings') {
        $settings = [
            'storage_path' => $_POST['storage_path'] ?? 'uploads/',
            'enable_versioning' => isset($_POST['enable_versioning']) ? 1 : 0,
            'auto_cleanup' => isset($_POST['auto_cleanup']) ? 1 : 0,
            'cleanup_days' => (int)($_POST['cleanup_days'] ?? 30),
            'compression_enabled' => isset($_POST['compression_enabled']) ? 1 : 0,
            'thumbnail_generation' => isset($_POST['thumbnail_generation']) ? 1 : 0
        ];
        
        foreach ($settings as $key => $value) {
            $type = is_int($value) ? 'integer' : 'string';
            $query = "INSERT INTO system_settings (setting_key, setting_value, setting_type, updated_by) 
                      VALUES (?, ?, ?, ?) 
                      ON DUPLICATE KEY UPDATE 
                      setting_value = VALUES(setting_value), 
                      setting_type = VALUES(setting_type),
                      updated_by = VALUES(updated_by),
                      updated_at = CURRENT_TIMESTAMP";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$key, $value, $type, $_SESSION['user_id']]);
        }
        
        $success_message = "Storage settings updated successfully.";
    }
    
    // Add department
    if ($action === 'add_department') {
        $dept_code = strtoupper(trim($_POST['department_code']));
        $dept_name = trim($_POST['department_name']);
        $description = trim($_POST['description']);
        $head_of_department = trim($_POST['head_of_department']);
        $contact_email = trim($_POST['contact_email']);
        
        $query = "INSERT INTO departments (department_code, department_name, description, head_of_department, contact_email) 
                  VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($query);
        if ($stmt->execute([$dept_code, $dept_name, $description, $head_of_department, $contact_email])) {
            $success_message = "Department added successfully.";
        } else {
            $error_message = "Failed to add department. Code might already exist.";
        }
    }
    
    // Update department status
    if ($action === 'toggle_department' && isset($_POST['dept_id'])) {
        $dept_id = (int)$_POST['dept_id'];
        $query = "UPDATE departments SET is_active = NOT is_active WHERE id = ?";
        $stmt = $pdo->prepare($query);
        if ($stmt->execute([$dept_id])) {
            $success_message = "Department status updated successfully.";
        } else {
            $error_message = "Failed to update department status.";
        }
    }
}

// Get current settings
function getSetting($pdo, $key, $default = '') {
    $query = "SELECT setting_value FROM system_settings WHERE setting_key = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['setting_value'] : $default;
}

// Get all departments
$dept_query = "SELECT * FROM departments ORDER BY department_name";
$dept_stmt = $pdo->prepare($dept_query);
$dept_stmt->execute();
$departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get system statistics for dashboard
$stats_query = "SELECT 
                   (SELECT COUNT(*) FROM users WHERE role != 'super_admin') as total_users,
                   (SELECT COUNT(*) FROM files WHERE is_deleted = 0) as total_files,
                   (SELECT COUNT(*) FROM folders WHERE is_deleted = 0) as total_folders,
                   (SELECT COUNT(*) FROM departments WHERE is_active = 1) as active_departments,
                   (SELECT SUM(file_size) FROM files WHERE is_deleted = 0) as total_storage";
$stats_stmt = $pdo->prepare($stats_query);
$stats_stmt->execute();
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?= time() ?>">
    <style>
        .settings-nav {
            border-right: 1px solid #dee2e6;
        }
        .settings-nav .nav-link {
            border-radius: 0;
            border: none;
            text-align: left;
            padding: 15px 20px;
        }
        .settings-nav .nav-link.active {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
        }
        .settings-nav .nav-link:hover:not(.active) {
            background-color: #f8f9fa;
        }
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .form-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
                <div class="d-flex justify-content-between align-items-center bg-white p-3 rounded shadow-sm">
                    <h2 class="mb-0"><i class="fas fa-cogs me-2 text-warning"></i>System Settings</h2>
                    <div class="text-muted">
                        Admin Panel Configuration
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

        <!-- System Overview -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card stat-card text-white bg-primary">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <h4><?php echo number_format($stats['total_users']); ?></h4>
                        <small>Total Users</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stat-card text-white bg-success">
                    <div class="card-body text-center">
                        <i class="fas fa-file fa-2x mb-2"></i>
                        <h4><?php echo number_format($stats['total_files']); ?></h4>
                        <small>Total Files</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stat-card text-white bg-warning">
                    <div class="card-body text-center">
                        <i class="fas fa-folder fa-2x mb-2"></i>
                        <h4><?php echo number_format($stats['total_folders']); ?></h4>
                        <small>Total Folders</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-white bg-info">
                    <div class="card-body text-center">
                        <i class="fas fa-building fa-2x mb-2"></i>
                        <h4><?php echo number_format($stats['active_departments']); ?></h4>
                        <small>Active Departments</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card text-white bg-dark">
                    <div class="card-body text-center">
                        <i class="fas fa-hdd fa-2x mb-2"></i>
                        <h4><?php echo formatFileSize($stats['total_storage']); ?></h4>
                        <small>Storage Used</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Settings Navigation -->
            <div class="col-md-3">
                <div class="card settings-nav">
                    <div class="list-group list-group-flush">
                        <button class="list-group-item list-group-item-action nav-link active" data-bs-toggle="tab" data-bs-target="#general-settings">
                            <i class="fas fa-cog me-2"></i>General Settings
                        </button>
                        <button class="list-group-item list-group-item-action nav-link" data-bs-toggle="tab" data-bs-target="#security-settings">
                            <i class="fas fa-shield-alt me-2"></i>Security
                        </button>
                        <button class="list-group-item list-group-item-action nav-link" data-bs-toggle="tab" data-bs-target="#storage-settings">
                            <i class="fas fa-hdd me-2"></i>Storage
                        </button>
                        <button class="list-group-item list-group-item-action nav-link" data-bs-toggle="tab" data-bs-target="#departments">
                            <i class="fas fa-building me-2"></i>Departments
                        </button>
                        <button class="list-group-item list-group-item-action nav-link" data-bs-toggle="tab" data-bs-target="#maintenance">
                            <i class="fas fa-tools me-2"></i>Maintenance
                        </button>
                    </div>
                </div>
            </div>

            <!-- Settings Content -->
            <div class="col-md-9">
                <div class="tab-content">
                    <!-- General Settings -->
                    <div class="tab-pane fade show active" id="general-settings">
                        <div class="card form-section">
                            <div class="card-header">
                                <h5><i class="fas fa-cog me-2"></i>General System Settings</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_system_settings">
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Site Name</label>
                                            <input type="text" class="form-control" name="site_name" 
                                                   value="<?php echo htmlspecialchars(getSetting($pdo, 'site_name', 'Document Management System')); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Admin Email</label>
                                            <input type="email" class="form-control" name="admin_email" 
                                                   value="<?php echo htmlspecialchars(getSetting($pdo, 'admin_email')); ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Site Description</label>
                                        <textarea class="form-control" name="site_description" rows="3"><?php echo htmlspecialchars(getSetting($pdo, 'site_description', 'A comprehensive document management system')); ?></textarea>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Max File Size (MB)</label>
                                            <input type="number" class="form-control" name="max_file_size" min="1" max="1024"
                                                   value="<?php echo getSetting($pdo, 'max_file_size', '100'); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Session Timeout (minutes)</label>
                                            <input type="number" class="form-control" name="session_timeout" min="5" max="480"
                                                   value="<?php echo getSetting($pdo, 'session_timeout', '30'); ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Allowed File Types</label>
                                        <input type="text" class="form-control" name="allowed_file_types" 
                                               placeholder="pdf,doc,docx,jpg,png,zip"
                                               value="<?php echo htmlspecialchars(getSetting($pdo, 'allowed_file_types', 'pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,gif,zip,rar')); ?>">
                                        <small class="text-muted">Comma-separated list of allowed file extensions</small>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="enable_public_registration"
                                                   <?php echo getSetting($pdo, 'enable_public_registration', '1') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Enable Public Registration</label>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="require_admin_approval"
                                                   <?php echo getSetting($pdo, 'require_admin_approval', '1') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Require Admin Approval for New Users</label>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="enable_email_notifications"
                                                   <?php echo getSetting($pdo, 'enable_email_notifications', '1') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Enable Email Notifications</label>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="maintenance_mode"
                                                   <?php echo getSetting($pdo, 'maintenance_mode', '0') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Maintenance Mode</label>
                                            <small class="text-muted d-block">When enabled, only admins can access the system</small>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save General Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Security Settings -->
                    <div class="tab-pane fade" id="security-settings">
                        <div class="card form-section">
                            <div class="card-header">
                                <h5><i class="fas fa-shield-alt me-2"></i>Security Settings</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_security_settings">
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Minimum Password Length</label>
                                            <input type="number" class="form-control" name="password_min_length" min="6" max="50"
                                                   value="<?php echo getSetting($pdo, 'password_min_length', '8'); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Max Login Attempts</label>
                                            <input type="number" class="form-control" name="max_login_attempts" min="1" max="10"
                                                   value="<?php echo getSetting($pdo, 'max_login_attempts', '3'); ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Account Lockout Duration (minutes)</label>
                                        <input type="number" class="form-control" name="lockout_duration" min="1" max="1440"
                                               value="<?php echo getSetting($pdo, 'lockout_duration', '15'); ?>">
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="password_require_special"
                                                   <?php echo getSetting($pdo, 'password_require_special', '1') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Require Special Characters in Passwords</label>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="enable_two_factor"
                                                   <?php echo getSetting($pdo, 'enable_two_factor', '0') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Enable Two-Factor Authentication</label>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="force_https"
                                                   <?php echo getSetting($pdo, 'force_https', '1') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Force HTTPS</label>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="session_security"
                                                   <?php echo getSetting($pdo, 'session_security', '1') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Enhanced Session Security</label>
                                            <small class="text-muted d-block">Regenerate session ID on login and privilege changes</small>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Security Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Storage Settings -->
                    <div class="tab-pane fade" id="storage-settings">
                        <div class="card form-section">
                            <div class="card-header">
                                <h5><i class="fas fa-hdd me-2"></i>Storage Settings</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_storage_settings">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Storage Path</label>
                                        <input type="text" class="form-control" name="storage_path"
                                               value="<?php echo htmlspecialchars(getSetting($pdo, 'storage_path', 'uploads/')); ?>">
                                        <small class="text-muted">Relative path from application root</small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Auto Cleanup (days)</label>
                                        <input type="number" class="form-control" name="cleanup_days" min="1" max="365"
                                               value="<?php echo getSetting($pdo, 'cleanup_days', '30'); ?>">
                                        <small class="text-muted">Automatically delete files in trash after specified days</small>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="enable_versioning"
                                                   <?php echo getSetting($pdo, 'enable_versioning', '0') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Enable File Versioning</label>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="auto_cleanup"
                                                   <?php echo getSetting($pdo, 'auto_cleanup', '1') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Enable Auto Cleanup</label>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="compression_enabled"
                                                   <?php echo getSetting($pdo, 'compression_enabled', '0') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Enable File Compression</label>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="thumbnail_generation"
                                                   <?php echo getSetting($pdo, 'thumbnail_generation', '1') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Auto Generate Thumbnails</label>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Storage Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Departments -->
                    <div class="tab-pane fade" id="departments">
                        <div class="row">
                            <!-- Add Department -->
                            <div class="col-md-5">
                                <div class="card form-section">
                                    <div class="card-header">
                                        <h5><i class="fas fa-plus me-2"></i>Add New Department</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="add_department">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Department Code</label>
                                                <input type="text" class="form-control" name="department_code" maxlength="10" required>
                                                <small class="text-muted">Short code (e.g., IT, HR, FIN)</small>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Department Name</label>
                                                <input type="text" class="form-control" name="department_name" maxlength="100" required>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Description</label>
                                                <textarea class="form-control" name="description" rows="3"></textarea>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Head of Department</label>
                                                <input type="text" class="form-control" name="head_of_department" maxlength="100">
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Contact Email</label>
                                                <input type="email" class="form-control" name="contact_email">
                                            </div>

                                            <button type="submit" class="btn btn-success w-100">
                                                <i class="fas fa-plus"></i> Add Department
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Departments List -->
                            <div class="col-md-7">
                                <div class="card form-section">
                                    <div class="card-header">
                                        <h5><i class="fas fa-building me-2"></i>All Departments</h5>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Code</th>
                                                        <th>Name</th>
                                                        <th>Head</th>
                                                        <th>Status</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($departments as $dept): ?>
                                                        <tr>
                                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($dept['department_code']); ?></span></td>
                                                            <td><?php echo htmlspecialchars($dept['department_name']); ?></td>
                                                            <td><?php echo htmlspecialchars($dept['head_of_department'] ?? '-'); ?></td>
                                                            <td>
                                                                <?php if ($dept['is_active']): ?>
                                                                    <span class="badge bg-success">Active</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-danger">Inactive</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="action" value="toggle_department">
                                                                    <input type="hidden" name="dept_id" value="<?php echo $dept['id']; ?>">
                                                                    <button type="submit" class="btn btn-sm <?php echo $dept['is_active'] ? 'btn-outline-danger' : 'btn-outline-success'; ?>">
                                                                        <i class="fas <?php echo $dept['is_active'] ? 'fa-pause' : 'fa-play'; ?>"></i>
                                                                        <?php echo $dept['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                                    </button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Maintenance -->
                    <div class="tab-pane fade" id="maintenance">
                        <div class="card form-section">
                            <div class="card-header">
                                <h5><i class="fas fa-tools me-2"></i>System Maintenance</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card border-warning">
                                            <div class="card-body text-center">
                                                <i class="fas fa-database fa-3x text-warning mb-3"></i>
                                                <h5>Database Optimization</h5>
                                                <p class="text-muted">Optimize database tables and clean up unnecessary data</p>
                                                <button class="btn btn-warning" onclick="runMaintenance('optimize_pdo')">
                                                    <i class="fas fa-cog"></i> Optimize Database
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="card border-danger">
                                            <div class="card-body text-center">
                                                <i class="fas fa-trash fa-3x text-danger mb-3"></i>
                                                <h5>Clear Trash</h5>
                                                <p class="text-muted">Permanently delete all files and folders in trash</p>
                                                <button class="btn btn-danger" onclick="runMaintenance('clear_trash')">
                                                    <i class="fas fa-trash"></i> Clear All Trash
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <div class="card border-info">
                                            <div class="card-body text-center">
                                                <i class="fas fa-broom fa-3x text-info mb-3"></i>
                                                <h5>Clear Cache</h5>
                                                <p class="text-muted">Clear system cache and temporary files</p>
                                                <button class="btn btn-info" onclick="runMaintenance('clear_cache')">
                                                    <i class="fas fa-broom"></i> Clear Cache
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="card border-success">
                                            <div class="card-body text-center">
                                                <i class="fas fa-download fa-3x text-success mb-3"></i>
                                                <h5>Export Data</h5>
                                                <p class="text-muted">Create backup of system data and files</p>
                                                <button class="btn btn-success" onclick="runMaintenance('export_data')">
                                                    <i class="fas fa-download"></i> Export Backup
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-warning mt-4">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Warning:</strong> Maintenance operations may affect system performance. 
                                    It's recommended to perform these operations during low-traffic periods.
                                </div>
                            </div>
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
        function runMaintenance(action) {
            if (confirm('Are you sure you want to run this maintenance operation? This action cannot be undone.')) {
                // Show loading indicator
                const button = event.target;
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                button.disabled = true;
                
                // Simulate maintenance operation (replace with actual AJAX call)
                setTimeout(() => {
                    alert('Maintenance operation completed successfully!');
                    button.innerHTML = originalText;
                    button.disabled = false;
                }, 3000);
                
                // In real implementation, you would make an AJAX call:
                /*
                fetch('maintenance.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=' + action
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    button.innerHTML = originalText;
                    button.disabled = false;
                })
                .catch(error => {
                    alert('Error: ' + error);
                    button.innerHTML = originalText;
                    button.disabled = false;
                });
                */
            }
        }
    </script>
</body>
</html>