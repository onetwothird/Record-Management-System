<?php include 'script/tracker.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Tracker - <?= htmlspecialchars($admin_department_name) ?></title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/doc-track.css?v=<?= time() ?>">
    <style>
        .department-info {
            background: linear-gradient(135deg, var(--blue), var(--light-blue));
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        .error-alert {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .success-alert {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .filter-advanced {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .faculty-actions {
            margin-top: 10px;
        }
        .faculty-actions button {
            margin-right: 5px;
            padding: 5px 10px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        .btn-message {
            background: var(--blue);
            color: white;
        }
        .btn-view {
            background: var(--light);
            color: var(--dark);
        }  

    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'components/sidebar.html'; ?>

    <!-- Content -->
    <section id="content">
        <!-- Navbar -->
        <?php include 'components/navbar.html'; ?>

        <!-- Main Content -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Document Submission Tracker</h1>
                    <ul class="breadcrumb">
                        <li><a href="dashboard.php">Admin</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Document Tracker</a></li>
                    </ul>
                </div>
            </div>

            <!-- Department Info -->
            <div class="department-info">
                <h2><?= htmlspecialchars($admin_department_name) ?></h2>
                <p>Document Submission Tracking System</p>
                <?php if ($admin_department_id): ?>
                    <small>Department ID: <?= htmlspecialchars($admin_department_id) ?></small>
                <?php endif; ?>
                <?php if (!$hasParentId): ?>
                    <div style="background: rgba(255,255,255,0.2); padding: 10px; border-radius: 5px; margin-top: 10px;">
                        <small><i class='bx bx-info-circle'></i> Hierarchical departments not enabled</small>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?= $total_faculty ?></h3>
                    <p>Total Faculty</p>
                    <small><?= $admin_department_id ? 'In Department' : 'System Wide' ?></small>
                </div>
                <div class="stat-card">
                    <h3><?= $submitted_count ?></h3>
                    <p>Documents Submitted</p>
                    <small>Out of <?= $total_possible ?> required</small>
                </div>
                <div class="stat-card">
                    <h3><?= $complete_faculty ?></h3>
                    <p>Complete Submissions</p>
                    <small><?= $faculty_completion_rate ?>% of faculty</small>
                </div>
                <div class="stat-card completion">
                    <h3><?= $completion_rate ?>%</h3>
                    <p>Overall Completion</p>
                    <small>Document submission rate</small>
                </div>
            </div>

            <!-- Advanced Filters -->
            <div class="filter-advanced" style="margin-bottom: 20px;">

            <!-- <div class="filter-group">
                    <label><strong>Academic Year:</strong></label>
                    <select name="year" class="form-control">
                        <option value="2023" ' . ($selectedYear == 2023 ? 'selected' : '') . '>2023</option>
                        <option value="2024" ' . ($selectedYear == 2024 ? 'selected' : '') . '>2024</option>
                        <option value="2025" ' . ($selectedYear == 2025 ? 'selected' : '') . '>2025</option>
                    </select>
                </div> -->
                
                <?php
                    echo '
                    <div class="filter-advanced" style="margin-bottom: 20px;">
                        <h4 style="margin-bottom: 15px;"><i class="bx bx-filter-alt"></i> Period Filter</h4>
                        <form method="GET" style="display: flex; gap: 15px; align-items: center;">
                            
                            
                            <div class="filter-group">
                                <label><strong>Semester:</strong></label>
                                <select name="semester" class="form-control">
                                    <option value="1st sem AY 2024-2025" ' . ($selectedSemester == '1st sem AY 2024-2025' ? 'selected' : '') . '>1st Semester AY 2024-2025</option>
                                    <option value="2nd sem AY 2024-2025" ' . ($selectedSemester == '2nd sem AY 2024-2025' ? 'selected' : '') . '>2nd Semester AY 2024-2025</option>
                                    <option value="1st sem AY 2025-2026" ' . ($selectedSemester == '1st sem AY 2025-2026' ? 'selected' : '') . '>1st Semester AY 2025-2026</option>
                                    <option value="2nd sem AY 2025-2026" ' . ($selectedSemester == '2nd sem AY 2025-2026' ? 'selected' : '') . '>2nd Semester AY 2025-2026</option>
                                </select>
                            </div>
                            
                            <!-- Preserve other filters -->
                            <input type="hidden" name="department" value="' . htmlspecialchars($department_filter) . '">
                            <input type="hidden" name="search" value="' . htmlspecialchars($search) . '">
                            <input type="hidden" name="status" value="' . htmlspecialchars($status_filter) . '">
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-filter"></i> Apply Filter
                            </button>
                        </form>
                    </div>';
                ?>
            </div>


            <!-- Document Table -->
            <div class="table-container">
                <div style="text-align: center; margin-bottom: 20px;">
                    <h2 style="margin: 0; color: var(--dark);">CAVITE STATE UNIVERSITY - NAIC CAMPUS</h2>
                    <p style="margin: 5px 0; color: var(--blue); font-weight: 600;">
                        <?= htmlspecialchars($admin_department_name) ?> - Document Submission Tracker
                    </p>
                    <p style="margin: 5px 0; color: var(--blue);"><?= htmlspecialchars($semester_options[$semester] ?? $semester) ?></p>
                    
                    <?php if (!empty($search) || !empty($department_filter) || !empty($status_filter)): ?>
                        <div style="margin: 10px 0; padding: 10px; background: #e3f2fd; border-radius: 5px;">
                            <small><strong>Active Filters:</strong>
                                <?php if (!empty($search)): ?>
                                    Search: "<?= htmlspecialchars($search) ?>"
                                <?php endif; ?>
                                <?php if (!empty($department_filter)): ?>
                                    <?php 
                                    $filtered_dept = array_filter($departments, function($d) use ($department_filter) {
                                        return $d['id'] == $department_filter;
                                    });
                                    $dept_name = !empty($filtered_dept) ? array_values($filtered_dept)[0]['department_name'] : 'Unknown';
                                    ?>
                                    | Department: "<?= htmlspecialchars($dept_name) ?>"
                                <?php endif; ?>
                                <?php if (!empty($status_filter)): ?>
                                    | Status: "<?= htmlspecialchars(ucfirst($status_filter)) ?>"
                                <?php endif; ?>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (empty($faculty)): ?>
                    <div style="text-align: center; padding: 60px 20px; color: #6c757d;">
                        <i class='bx bx-user-x' style="font-size: 64px; display: block; margin-bottom: 20px; opacity: 0.5;"></i>
                        <h3>No Faculty Members Found</h3>
                        <?php if (!empty($search) || !empty($department_filter) || !empty($status_filter)): ?>
                            <p>No faculty members match your current filters.</p>
                            <a href="document-tracker.php" class="btn btn-primary">Clear Filters</a>
                        <?php else: ?>
                            <p>No faculty members are assigned to your department: <strong><?= htmlspecialchars($admin_department_name) ?></strong></p>
                            <?php if ($admin_department_id): ?>
                                <p><small>Department ID: <?= htmlspecialchars($admin_department_id) ?></small></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div style="margin-bottom: 15px; text-align: right;">
                        <small style="color: #6c757d;">
                            Showing <?= count($faculty) ?> faculty member(s) | 
                            Last updated: <?= date('M d, Y h:i A') ?>
                        </small>
                    </div>
                    
                    <table class="doc-table" id="documentTable">
                        <thead>
                            <tr>
                                <th rowspan="2" class="faculty-cell" style="min-width: 200px;">
                                    FACULTY STAFF
                                    <small style="display: block; font-weight: normal;">Click for details</small>
                                </th>
                                <th colspan="<?= count($document_types) ?>">DOCUMENTS (Auto-tracked by File Uploads)</th>
                                <th rowspan="2" style="min-width: 100px;">PROGRESS</th>
                            </tr>
                            <tr>
                                <?php foreach ($document_types as $doc_type): ?>
                                    <th title="<?= htmlspecialchars($doc_type) ?>" style="writing-mode: vertical-lr; text-orientation: mixed; min-width: 40px;">
                                        <span style="writing-mode: horizontal-tb;">
                                            <?= strlen($doc_type) > 15 ? substr($doc_type, 0, 15) . '...' : $doc_type ?>
                                        </span>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($faculty as $staff): 
                                $staff_submitted = 0;
                                foreach ($document_types as $dt) {
                                    if (isset($file_submissions[$staff['id']][$dt])) {
                                        $staff_submitted++;
                                    }
                                }
                                $staff_progress = count($document_types) > 0 ? round(($staff_submitted / count($document_types)) * 100) : 0;
                            ?>
                            <tr data-faculty-id="<?= $staff['id'] ?>" data-progress="<?= $staff_progress ?>">
                                    <td class="faculty-cell" onclick="viewFacultyDetails(<?= $staff['id'] ?>)" style="cursor: pointer;">
                                        <div style="padding: 10px;">
                                            <strong><?= htmlspecialchars($staff['surname'] . ', ' . $staff['name']) ?>
                                            <?= !empty($staff['mi']) ? ' ' . htmlspecialchars($staff['mi']) . '.' : '' ?></strong>
                                            
                                            <div style="margin-top: 5px; font-size: 12px; color: #666;">
                                                <div><i class='bx bx-building'></i> <?= htmlspecialchars($staff['department_name'] ?? 'No Department') ?></div>
                                                
                                                <?php if (!empty($staff['employee_id'])): ?>
                                                    <div><i class='bx bx-id-card'></i> ID: <?= htmlspecialchars($staff['employee_id']) ?></div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($staff['position'])): ?>
                                                    <div><i class='bx bx-user-circle'></i> <?= htmlspecialchars($staff['position']) ?></div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($staff['email'])): ?>
                                                    <div><i class='bx bx-envelope'></i> <?= htmlspecialchars($staff['email']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="faculty-actions">
                                                <button class="btn-message" onclick="event.stopPropagation(); sendMessage(<?= $staff['id'] ?>)" title="Send Message">
                                                    <i class='bx bx-message'></i>
                                                </button>
                                                <button class="btn-view" onclick="event.stopPropagation(); viewAllSubmissions(<?= $staff['id'] ?>)" title="View All Files">
                                                    <i class='bx bx-file'></i>
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <?php foreach ($document_types as $doc_type): ?>
                                    <?php 
                                        $file_submission = $file_submissions[$staff['id']][$doc_type] ?? null;
                                        $has_files = !empty($file_submission);
                                        
                                        $cell_class = 'status-cell';
                                        if ($has_files) {
                                            $cell_class .= ' submitted';
                                        } else {
                                            $cell_class .= ' not-submitted';
                                    }
                                    ?>
                                    <td class="<?= $cell_class ?>" 
                                        onclick="<?= $has_files ? 
                                            'viewDetails('.$staff['id'].',\''.htmlspecialchars($doc_type, ENT_QUOTES).'\',\''.htmlspecialchars($normalizedSemester, ENT_QUOTES).'\','.$selectedYear.')' : 
                                            'showNotSubmittedDetails('.$staff['id'].',\''.htmlspecialchars($doc_type, ENT_QUOTES).'\',\''.htmlspecialchars($normalizedSemester, ENT_QUOTES).'\','.$selectedYear.')' 
                                        ?>"
                                        style="cursor: pointer; text-align: center; padding: 8px;">
                                        
                                        <?php if ($has_files): ?>
                                            <div class="file-count-badge" title="<?= $file_submission['file_count'] ?> file(s) uploaded">
                                                <?= $file_submission['file_count'] ?>
                                            </div>
                                            
                                            <div class="status-indicator submitted">
                                                <i class='bx bx-check'></i>
                                            </div>
                                            
                                            <div class="submission-info">
                                                <small title="Latest upload: <?= date('M d, Y h:i A', strtotime($file_submission['latest_upload'])) ?>">
                                                    <?= date('m/d/Y', strtotime($file_submission['latest_upload'])) ?>
                                                </small>
                                                <?php if ($file_submission['total_size'] > 0): ?>
                                                    <small style="display: block;"><?= formatFileSize($file_submission['total_size']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="status-indicator not-submitted">
                                                <i class='bx bx-x'></i>
                                            </div>
                                            
                                            <div class="submission-info">
                                                <small>Not Submitted</small>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                    
                                    <td style="text-align: center; padding: 10px;">
                                        <div class="progress-circle" data-progress="<?= $staff_progress ?>">
                                            <svg width="50" height="50">
                                                <circle cx="25" cy="25" r="20" fill="none" stroke="#e0e0e0" stroke-width="4"/>
                                                <circle cx="25" cy="25" r="20" fill="none" 
                                                        stroke="<?= $staff_progress == 100 ? '#28a745' : ($staff_progress >= 50 ? '#ffc107' : '#dc3545') ?>" 
                                                        stroke-width="4" 
                                                        stroke-dasharray="<?= 2 * M_PI * 20 ?>" 
                                                        stroke-dashoffset="<?= 2 * M_PI * 20 * (1 - $staff_progress / 100) ?>"
                                                        transform="rotate(-90 25 25)"/>
                                                <text x="25" y="25" text-anchor="middle" dy="0.3em" font-size="10" font-weight="bold">
                                                    <?= $staff_progress ?>%
                                                </text>
                                            </svg>
                                        </div>
                                        <small><?= $staff_submitted ?>/<?= count($document_types) ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </section>

    <!-- File Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="detailsModalTitle">Document Files</h3>
                <button class="close-btn" onclick="closeDetailsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="detailsContent"></div>
            </div>
        </div>
    </div>

    <!-- Not Submitted Details Modal -->
    <div id="notSubmittedModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Document Submission Details</h3>
                <button class="close-btn" onclick="closeNotSubmittedModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="notSubmittedContent"></div>
            </div>
        </div>
    </div>

    <!-- Faculty Details Modal -->
    <div id="facultyModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3>Faculty Details</h3>
                <button class="close-btn" onclick="closeFacultyModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="facultyContent"></div>
            </div>
        </div>
    </div>

    <!-- Bulk Actions Modal -->
    <div id="bulkModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Bulk Actions</h3>
                <button class="close-btn" onclick="closeBulkModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="bulkContent"></div>
            </div>
        </div>
    </div>
    <script src="assets/js/script.js?v=<?= time() ?>"></script>
    <script>
        // JavaScript functions (keeping most of the original JavaScript)
        function viewDetails(facultyId, docType, semester) {
            const modal = document.getElementById('detailsModal');
            const title = document.getElementById('detailsModalTitle');
            const content = document.getElementById('detailsContent');
            
            title.textContent = `Files for ${docType}`;
            content.innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <i class='bx bx-loader-alt bx-spin' style="font-size: 24px;"></i>
                    <p>Loading file details...</p>
                </div>
            `;
            
            modal.style.display = 'block';
            
            fetch(`?action=get_file_details&faculty_id=${facultyId}&document_type=${encodeURIComponent(docType)}&semester=${encodeURIComponent(semester)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.files && data.files.length > 0) {
                    let html = '<div class="file-list">';
                    data.files.forEach(file => {
                        html += `
                            <div class="file-item" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; border-radius: 5px;">
                                <h4><i class='bx bx-file'></i> ${file.file_name}</h4>
                                <p><strong>Size:</strong> ${formatFileSize(file.file_size || 0)}</p>
                                <p><strong>Uploaded:</strong> ${new Date(file.uploaded_at).toLocaleString()}</p>
                                <p><strong>Type:</strong> ${file.document_type}</p>
                                <div style="margin-top: 10px;">
                                    <a href="handler/download_file.php?id=${file.id}" class="btn btn-primary" style="margin-right: 10px;">
                                        <i class='bx bx-download'></i> Download
                                    </a>
                                    <button class="btn btn-info" onclick="previewFile(${file.id})">
                                        <i class='bx bx-show'></i> Preview
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    content.innerHTML = html;
                } else {
                    content.innerHTML = '<p class="error">No files found or error loading files.</p>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                content.innerHTML = '<p class="error">Network error occurred</p>';
            });
        }

        function downloadFile(fileId) {
            window.open(`handler/download_file.php?id=${fileId}`, '_blank');
        }

        function showNotSubmittedDetails(facultyId, docType, semester, academicYear = null) {
            const modal = document.getElementById('notSubmittedModal');
            const content = document.getElementById('notSubmittedContent');
            
            if (!academicYear) {
                academicYear = new Date().getFullYear();
            }
            
            content.innerHTML = `
                <div class="not-submitted-info">
                    <h4><i class='bx bx-x-circle' style="color: #dc3545;"></i> Document Not Submitted</h4>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;">
                        <p style="margin: 5px 0;"><strong>Document Type:</strong> ${docType}</p>
                        <p style="margin: 5px 0;"><strong>Academic Year:</strong> ${academicYear}</p>
                        <p style="margin: 5px 0;"><strong>Semester:</strong> ${semester}</p>
                    </div>
                    <p style="color: #6c757d;">This faculty member has not uploaded any files for this document type in the specified period.</p>
                    
                    <div class="action-buttons" style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                        <button class="btn btn-warning" onclick="sendReminder(${facultyId}, '${docType}', '${semester}', ${academicYear})" style="padding: 8px 15px;">
                            <i class='bx bx-bell'></i> Send Reminder
                        </button>
                        <button class="btn btn-info" onclick="addNote(${facultyId}, '${docType}', '${semester}', ${academicYear})" style="padding: 8px 15px;">
                            <i class='bx bx-note'></i> Add Note
                        </button>
                        <button class="btn btn-secondary" onclick="setDeadline(${facultyId}, '${docType}', '${semester}', ${academicYear})" style="padding: 8px 15px;">
                            <i class='bx bx-calendar'></i> Set Deadline
                        </button>
                    </div>
                </div>
            `;
            
            modal.style.display = 'block';
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').style.display = 'none';
        }

        function closeNotSubmittedModal() {
            document.getElementById('notSubmittedModal').style.display = 'none';
        }

        function closeFacultyModal() {
            document.getElementById('facultyModal').style.display = 'none';
        }

        function closeBulkModal() {
            document.getElementById('bulkModal').style.display = 'none';
        }

        function searchFaculty(event) {
            event.preventDefault();
            const searchTerm = event.target.search.value;
            const url = new URL(window.location);
            if (searchTerm.trim()) {
                url.searchParams.set('search', searchTerm);
            } else {
                url.searchParams.delete('search');
            }
            window.location.href = url.toString();
        }

        function exportTable() {
            const table = document.getElementById('documentTable');
            if (!table) {
                alert('No table found to export');
                return;
            }
            
            const departmentName = '<?= htmlspecialchars($admin_department_name) ?>';
            const semester = '<?= htmlspecialchars($semester) ?>';
            
            let csv = 'CAVITE STATE UNIVERSITY - NAIC CAMPUS\n';
            csv += `${departmentName} - Document Submission Tracker\n`;
            csv += `${semester}\n\n`;
            
            const headers = ['Faculty Name', 'Department', 'Employee ID', 'Email'];
            const documentTypes = <?= json_encode($document_types) ?>;
            documentTypes.forEach(docType => headers.push(docType));
            headers.push('Progress %', 'Completed Count');
            csv += headers.map(h => `"${h}"`).join(',') + '\n';

            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const rowData = [];
                
                const facultyText = cells[0].textContent.trim();
                const lines = facultyText.split('\n').map(line => line.trim()).filter(line => line);
                const name = lines[0] || 'Unknown';
                const department = lines.find(line => line.includes('Department') || line.includes('CEIT')) || '';
                const employeeId = lines.find(line => line.includes('ID:')) || '';
                const email = lines.find(line => line.includes('@')) || '';  
                
                rowData.push(name, department.replace(/[^\w\s-]/g, ''), employeeId.replace('ID:', '').trim(), email);
                
                const statusCells = Array.from(cells).slice(1, -1); 
                statusCells.forEach(cell => {
                    const isSubmitted = cell.classList.contains('submitted');
                    const fileCount = cell.querySelector('.file-count-badge')?.textContent || '0';
                    rowData.push(isSubmitted ? `SUBMITTED (${fileCount})` : 'NOT SUBMITTED');
                });
                
                const progress = row.getAttribute('data-progress') || '0';
                const completedCount = statusCells.filter(cell => cell.classList.contains('submitted')).length;
                rowData.push(progress + '%', completedCount);
                
                csv += rowData.map(data => `"${String(data).replace(/"/g, '""')}"`).join(',') + '\n';
            });

            csv += '\n"SUMMARY"\n';
            csv += `"Total Faculty","<?= $total_faculty ?>"\n`;
            csv += `"Documents Submitted","<?= $submitted_count ?>"\n`;
            csv += `"Total Required","<?= $total_possible ?>"\n`;
            csv += `"Overall Completion Rate","<?= $completion_rate ?>%"\n`;
            csv += `"Faculty with Complete Submissions","<?= $complete_faculty ?>"\n`;
            csv += `"Faculty Completion Rate","<?= $faculty_completion_rate ?>%"\n`;

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            
            const filename = `document_tracker_${departmentName.replace(/[^\w\s-]/g, '').replace(/\s+/g, '_')}_${new Date().toISOString().split('T')[0]}.csv`;
            link.setAttribute('download', filename);
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }

        function generateReport() {
            window.open(`generate_report.php?department=${<?= json_encode($admin_department_id) ?>}&semester=${encodeURIComponent(<?= json_encode($semester) ?>)}`, '_blank');
        }

        function refreshData() {
            window.location.reload();
        }

        function formatFileSize(bytes) {
            if (bytes >= 1073741824) {
                return (bytes / 1073741824).toFixed(2) + ' GB';
            } else if (bytes >= 1048576) {
                return (bytes / 1048576).toFixed(2) + ' MB';
            } else if (bytes >= 1024) {
                return (bytes / 1024).toFixed(2) + ' KB';
            } else if (bytes > 1) {
                return bytes + ' bytes';
            } else if (bytes == 1) {
                return '1 byte';
            } else {
                return '0 bytes';
            }
        }

        // Additional JavaScript functions
        function viewFacultyDetails(facultyId) {
            const modal = document.getElementById('facultyModal');
            const content = document.getElementById('facultyContent');
            
            content.innerHTML = `
                <div style="text-align: center; padding: 20px;">
                    <i class='bx bx-loader-alt bx-spin' style="font-size: 24px;"></i>
                    <p>Loading faculty details...</p>
                </div>
            `;
            
            modal.style.display = 'block';
            
            // You can add API call here to fetch faculty details
            setTimeout(() => {
                content.innerHTML = `
                    <div style="text-align: center; padding: 20px;">
                        <p>Faculty details functionality can be implemented here.</p>
                        <p>Faculty ID: ${facultyId}</p>
                    </div>
                `;
            }, 1000);
        }

        function sendMessage(facultyId) {
            const message = prompt('Enter message to send to faculty:');
            if (!message) return;
            
            // Implementation for sending message
            alert('Message functionality to be implemented');
        }

        function viewAllSubmissions(facultyId) {
            // Implementation for viewing all submissions
            alert('View all submissions functionality to be implemented');
        }

        function sendReminder(facultyId, docType, semester, academicYear) {
            if (!confirm(`Send reminder about ${docType} for ${academicYear} - ${semester}?`)) return;
            
            // You can implement this to send actual reminders
            fetch('send_reminder.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    faculty_id: facultyId,
                    document_type: docType,
                    semester: semester,
                    academic_year: academicYear,
                    action: 'send_reminder'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Reminder sent successfully!');
                    closeNotSubmittedModal();
                } else {
                    alert('Failed to send reminder: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error occurred while sending reminder');
            });
        }

        function addNote(facultyId, docType, semester, academicYear) {
            const note = prompt('Enter note:');
            if (!note || note.trim() === '') return;
            alert('Add note functionality to be implemented');
        }

        function setDeadline(facultyId, docType, semester, academicYear) {
            const deadline = prompt('Enter deadline (YYYY-MM-DD):');
            if (!deadline) return;
            
            // Validate date format
            const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
            if (!dateRegex.test(deadline)) {
                alert('Please enter date in YYYY-MM-DD format');
                return;
            }
            
            // Implement deadline functionality
            alert('Set deadline functionality to be implemented');
        }

        function previewFile(fileId) {
            alert('File preview functionality to be implemented');
        }

        function saveFilters() {
            const formData = new FormData(document.getElementById('filterForm'));
            const filters = {};
            for (let [key, value] of formData.entries()) {
                filters[key] = value;
            }
            localStorage.setItem('documentTrackerFilters', JSON.stringify(filters));
            alert('Filters saved successfully');
        }

        // Event listeners
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                closeDetailsModal();
                closeNotSubmittedModal();
                closeFacultyModal();
                closeBulkModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDetailsModal();
                closeNotSubmittedModal();
                closeFacultyModal();
                closeBulkModal();
            }
        });
    </script>
</body>
</html>