<?php include 'assets/script/departments-script.php'?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Management - CVSU NAIC</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <style>
        .dept-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .dept-card {
            background: var(--light);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            border: 2px solid transparent;
        }
        
        .dept-card:hover {
            transform: translateY(-2px);
            border-color: var(--blue);
        }
        
        .dept-card.inactive {
            opacity: 0.7;
            border-color: #ccc;
        }
        
        .dept-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .dept-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: linear-gradient(45deg, var(--blue), var(--light-blue));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }
        
        .dept-status {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .dept-info h3 {
            margin: 15px 0 5px 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .dept-code {
            color: var(--blue);
            font-weight: 500;
            font-size: 14px;
        }
        
        .dept-description {
            color: #666;
            font-size: 13px;
            line-height: 1.5;
            margin: 10px 0;
        }
        
        .dept-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 15px 0;
            padding: 15px;
            background: var(--grey);
            border-radius: 8px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 20px;
            font-weight: 600;
            color: var(--blue);
            display: block;
        }
        
        .stat-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            margin-top: 2px;
        }
        
        .dept-contact {
            font-size: 12px;
            color: #666;
            margin: 10px 0;
        }
        
        .dept-contact div {
            margin-bottom: 5px;
        }
        
        .dept-contact i {
            width: 16px;
            margin-right: 5px;
            color: var(--blue);
        }
        
        .dept-actions {
            display: flex;
            gap: 8px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid var(--grey);
        }
        
        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
        }
        
        .btn-primary { background: var(--blue); color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .btn:hover { opacity: 0.8; transform: translateY(-1px); }
        
        .filters {
            display: flex;
            gap: 15px;
            align-items: center;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .filter-tabs {
            display: flex;
            background: var(--grey);
            border-radius: 8px;
            padding: 4px;
        }
        
        .filter-tab {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            color: var(--dark);
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .filter-tab.active {
            background: var(--blue);
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal-content {
            background: var(--light);
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            margin: 50px auto;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 20px;
        }
        
        .close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--blue);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-group.checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-group.checkbox input {
            width: auto;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 30px;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            color: var(--dark);
            border: 1px solid #ddd;
        }
        
        .pagination a:hover {
            background: var(--blue);
            color: white;
            border-color: var(--blue);
        }
        
        .pagination .current {
            background: var(--blue);
            color: white;
            border-color: var(--blue);
        }
        
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .overview-card {
            background: var(--light);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .overview-card i {
            font-size: 32px;
            margin-bottom: 10px;
            color: var(--blue);
        }
        
        .overview-card h3 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .overview-card p {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .dept-grid {
                grid-template-columns: 1fr;
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .dept-stats {
                grid-template-columns: repeat(4, 1fr);
                gap: 10px;
            }
        }
    </style>
</head>
<body>
<!-- Sidebar Component -->
    <?php include 'components/sidebar.html'; ?>

    <!-- Content -->
    <section id="content">
        <!-- Navbar Component -->
        <?php include 'components/navbar.html'; ?>

        <!-- Main Content -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Department Management</h1>
                    <ul class="breadcrumb">
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Departments</a></li>
                    </ul>
                </div>
                <button onclick="openModal('createDeptModal')" class="btn-download">
                    <i class='bx bx-buildings'></i>
                    <span class="text">Add Department</span>
                </button>
            </div>

            <!-- Alerts -->
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class='bx bx-check-circle'></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class='bx bx-error-circle'></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Overview -->
            <div class="stats-overview">
                <div class="overview-card">
                    <i class='bx bxs-buildings'></i>
                    <h3><?php echo $counts['total']; ?></h3>
                    <p>Total Departments</p>
                </div>
                <div class="overview-card">
                    <i class='bx bxs-check-circle'></i>
                    <h3><?php echo $counts['active']; ?></h3>
                    <p>Active Departments</p>
                </div>
                <div class="overview-card">
                    <i class='bx bxs-x-circle'></i>
                    <h3><?php echo $counts['inactive']; ?></h3>
                    <p>Inactive Departments</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <div class="filter-tabs">
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'all', 'page' => 1])); ?>" 
                       class="filter-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                        All (<?php echo $counts['total']; ?>)
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'active', 'page' => 1])); ?>" 
                       class="filter-tab <?php echo $status_filter === 'active' ? 'active' : ''; ?>">
                        Active (<?php echo $counts['active']; ?>)
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'inactive', 'page' => 1])); ?>" 
                       class="filter-tab <?php echo $status_filter === 'inactive' ? 'active' : ''; ?>">
                        Inactive (<?php echo $counts['inactive']; ?>)
                    </a>
                </div>
            </div>

            <!-- Department Grid -->
            <div class="dept-grid">
                <?php foreach ($departments as $dept): ?>
                    <div class="dept-card <?php echo $dept['is_active'] ? '' : 'inactive'; ?>">
                        <div class="dept-header">
                            <div class="dept-icon">
                                <i class='bx bxs-buildings'></i>
                            </div>
                            <div class="dept-status status-<?php echo $dept['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $dept['is_active'] ? 'Active' : 'Inactive'; ?>
                            </div>
                        </div>
                        
                        <div class="dept-info">
                            <div class="dept-code"><?php echo htmlspecialchars($dept['department_code']); ?></div>
                            <h3><?php echo htmlspecialchars($dept['department_name']); ?></h3>
                            <?php if ($dept['description']): ?>
                                <div class="dept-description"><?php echo htmlspecialchars($dept['description']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="dept-stats">
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $dept['user_count']; ?></span>
                                <div class="stat-label">Users</div>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $dept['folder_count']; ?></span>
                                <div class="stat-label">Folders</div>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $dept['file_count']; ?></span>
                                <div class="stat-label">Files</div>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo formatFileSize($dept['total_size']); ?></span>
                                <div class="stat-label">Storage</div>
                            </div>
                        </div>

                        <?php if ($dept['head_of_department'] || $dept['contact_email'] || $dept['contact_phone']): ?>
                            <div class="dept-contact">
                                <?php if ($dept['head_of_department']): ?>
                                    <div><i class='bx bx-user'></i> <?php echo htmlspecialchars($dept['head_of_department']); ?></div>
                                <?php endif; ?>
                                <?php if ($dept['contact_email']): ?>
                                    <div><i class='bx bx-envelope'></i> <?php echo htmlspecialchars($dept['contact_email']); ?></div>
                                <?php endif; ?>
                                <?php if ($dept['contact_phone']): ?>
                                    <div><i class='bx bx-phone'></i> <?php echo htmlspecialchars($dept['contact_phone']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="dept-actions">
                            <button onclick="editDepartment(<?php echo $dept['id']; ?>)" class="btn btn-primary">
                                <i class='bx bx-edit'></i> Edit
                            </button>
                            
                            <button onclick="toggleStatus(<?php echo $dept['id']; ?>)" 
                                    class="btn <?php echo $dept['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                <i class='bx <?php echo $dept['is_active'] ? 'bx-pause' : 'bx-play'; ?>'></i>
                                <?php echo $dept['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </button>
                            
                            <?php if ($dept['user_count'] == 0 && $dept['folder_count'] == 0): ?>
                                <button onclick="deleteDepartment(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars($dept['department_name']); ?>')" 
                                        class="btn btn-danger">
                                    <i class='bx bx-trash'></i> Delete
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($departments)): ?>
                <div style="text-align: center; padding: 60px; color: #666;">
                    <i class='bx bx-buildings' style="font-size: 64px; margin-bottom: 20px; display: block;"></i>
                    <h3>No departments found</h3>
                    <p>Try adjusting your search criteria or create a new department</p>
                    <button onclick="openModal('createDeptModal')" class="btn btn-primary" style="margin-top: 15px;">
                        <i class='bx bx-plus'></i> Add Department
                    </button>
                </div>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">&laquo; Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </section>

    <!-- Create Department Modal -->
    <div id="createDeptModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Department</h3>
                <button type="button" class="close" onclick="closeModal('createDeptModal')">&times;</button>
            </div>
            <form method="POST" action="?action=create">
                <div class="form-row">
                    <div class="form-group">
                        <label for="department_code">Department Code *</label>
                        <input type="text" id="department_code" name="department_code" maxlength="10" required 
                               style="text-transform: uppercase;" placeholder="e.g., ITD">
                        <small style="color: #666;">Max 10 characters, will be converted to uppercase</small>
                    </div>
                    <div class="form-group">
                        <label for="department_name">Department Name *</label>
                        <input type="text" id="department_name" name="department_name" required 
                               placeholder="e.g., Information Technology Department">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3" 
                              placeholder="Brief description of the department..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="head_of_department">Head of Department</label>
                    <input type="text" id="head_of_department" name="head_of_department" 
                           placeholder="e.g., Dr. John Smith">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="contact_email">Contact Email</label>
                        <input type="email" id="contact_email" name="contact_email" 
                               placeholder="department@cvsu.edu.ph">
                    </div>
                    <div class="form-group">
                        <label for="contact_phone">Contact Phone</label>
                        <input type="tel" id="contact_phone" name="contact_phone" 
                               placeholder="+63-2-1234-5678">
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 30px;">
                    <button type="button" onclick="closeModal('createDeptModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Department</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Department Modal -->
    <div id="editDeptModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Department</h3>
                <button type="button" class="close" onclick="closeModal('editDeptModal')">&times;</button>
            </div>
            <form method="POST" action="?action=update" id="editDeptForm">
                <input type="hidden" id="edit_department_id" name="department_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_department_code">Department Code *</label>
                        <input type="text" id="edit_department_code" name="department_code" maxlength="10" required 
                               style="text-transform: uppercase;">
                    </div>
                    <div class="form-group">
                        <label for="edit_department_name">Department Name *</label>
                        <input type="text" id="edit_department_name" name="department_name" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit_head_of_department">Head of Department</label>
                    <input type="text" id="edit_head_of_department" name="head_of_department">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_contact_email">Contact Email</label>
                        <input type="email" id="edit_contact_email" name="contact_email">
                    </div>
                    <div class="form-group">
                        <label for="edit_contact_phone">Contact Phone</label>
                        <input type="tel" id="edit_contact_phone" name="contact_phone">
                    </div>
                </div>
                
                <div class="form-group checkbox">
                    <input type="checkbox" id="edit_is_active" name="is_active">
                    <label for="edit_is_active">Department Active</label>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 30px;">
                    <button type="button" onclick="closeModal('editDeptModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Department</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <script src="assets/js/departments.js"></script>
</body>
</html>