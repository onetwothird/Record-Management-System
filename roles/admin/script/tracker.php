<?php
    require_once '../../includes/config.php';
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }

    $currentUser = getCurrentUser($pdo);
    if (!$currentUser || !$currentUser['is_approved']) {
        header('Location: logout.php');
        exit();
    }

    if ($currentUser['role'] !== 'admin') {
        header('Location: ../admin/dashboard.php');
        exit();
    }

    $admin_department_id = null;
    $admin_department_name = 'Unknown Department';

    try {
        if (isset($currentUser['department_id']) && !empty($currentUser['department_id'])) {
            $admin_department_id = $currentUser['department_id'];
        }

        if (!$admin_department_id) {
            $stmt = $pdo->prepare("
                SELECT u.department_id, d.department_name, d.department_code
                FROM users u 
                LEFT JOIN departments d ON u.department_id = d.id 
                WHERE u.id = ? AND u.role = 'admin'
            ");
            $stmt->execute([$currentUser['id']]);
            $user_dept = $stmt->fetch();
            
            if ($user_dept && $user_dept['department_id']) {
                $admin_department_id = $user_dept['department_id'];
                $admin_department_name = $user_dept['department_name'] ?? 'Department ' . $admin_department_id;
            }
        } else {
            $stmt = $pdo->prepare("SELECT department_name, department_code FROM departments WHERE id = ?");
            $stmt->execute([$admin_department_id]);
            $dept = $stmt->fetch();
            if ($dept) {
                $admin_department_name = $dept['department_name'];
            }
        }

        if (!$admin_department_id) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as admin_count FROM users WHERE role = 'admin'");
            $stmt->execute();
            $admin_count = $stmt->fetch()['admin_count'];

            if ($admin_count == 1) {
                $admin_department_name = 'System Administrator (All Departments)';
            } else {
                die("
                    <div style='text-align: center; padding: 50px; font-family: Arial, sans-serif;'>
                        <h2 style='color: #dc3545;'>Department Assignment Required</h2>
                        <p>Your admin account is not assigned to any department.</p>
                        <p>Please contact the system administrator to assign your account to a department.</p>
                        <a href='logout.php' style='color: #007bff; text-decoration: none;'>← Logout and Contact Admin</a>
                    </div>
                ");
            }
        }
        
    } catch (Exception $e) {
        error_log("Department ID Error for Admin " . $currentUser['id'] . ": " . $e->getMessage());
        die("
            <div style='text-align: center; padding: 50px; font-family: Arial, sans-serif;'>
                <h2 style='color: #dc3545;'>System Error</h2>
                <p>Unable to determine department assignment. Please contact system administrator.</p>
                <p><small>Error ID: " . uniqid() . "</small></p>
                <a href='logout.php' style='color: #007bff; text-decoration: none;'>← Logout</a>
            </div>
        ");
    }

    // Check if parent_id column exists in departments table
    $hasParentId = false;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM departments LIKE 'parent_id'");
        $hasParentId = $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Error checking parent_id column: " . $e->getMessage());
        $hasParentId = false;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_file_details') {
        header('Content-Type: application/json');
        
        try {
            $faculty_id = $_GET['faculty_id'] ?? 0;
            $doc_type = $_GET['document_type'] ?? '';
            $semester = $_GET['semester'] ?? '';
            $academic_year = $_GET['academic_year'] ?? date('Y');
            
            // Convert semester format if needed (from UI format to DB format)
            if (strpos($semester, 'AY') !== false) {
                $semesterMap = [
                    '1st sem AY 2024-2025' => '1st Semester',
                    '2nd sem AY 2024-2025' => '2nd Semester',
                    '1st sem AY 2025-2026' => '1st Semester',
                    '2nd sem AY 2025-2026' => '2nd Semester'
                ];
                $semester = $semesterMap[$semester] ?? '2nd Semester';
            } else {
                $faculty_check_query = "
                    SELECT u.id, u.department_id, d.department_name, NULL as parent_id
                    FROM users u
                    LEFT JOIN departments d ON u.department_id = d.id
                    WHERE u.id = ? AND u.role = 'user'
                ";
            }

            $stmt = $pdo->prepare($faculty_check_query);
            $stmt->execute([$faculty_id]);
            $faculty_dept = $stmt->fetch();
            
            if (!$faculty_dept) {
                throw new Exception('Faculty member not found');
            }

            $can_view = false;
            if (!$admin_department_id) {
                $can_view = true;
            } else {
                if ($faculty_dept['department_id'] == $admin_department_id || 
                    ($hasParentId && $faculty_dept['parent_id'] == $admin_department_id)) {
                    $can_view = true;
                }
            }
            
            if (!$can_view) {
                throw new Exception('Access denied: Faculty not in your department');
            }
            
            // UPDATED QUERY - Now uses the same structure as user system
            $stmt = $pdo->prepare("
                SELECT 
                    df.id, df.file_name, df.file_path, df.file_size, 
                    df.uploaded_at, df.description,
                    fds.document_type, fds.semester, fds.academic_year,
                    CONCAT(u.name, ' ', u.surname) as uploader_name,
                    u.employee_id
                FROM document_files df
                INNER JOIN faculty_document_submissions fds ON df.submission_id = fds.id
                INNER JOIN users u ON fds.faculty_id = u.id
                WHERE fds.faculty_id = ? 
                AND fds.document_type = ?
                AND fds.semester = ?
                AND fds.academic_year = ?
                ORDER BY df.uploaded_at DESC
            ");
            $stmt->execute([$faculty_id, $doc_type, $semester, $academic_year]);
            $files = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'files' => $files]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    $semester = $_GET['semester'] ?? '2nd sem AY 2024-2025';
    $department_filter = $_GET['department'] ?? '';
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status'] ?? ''; 

    $document_types = [
        'IPCR Accomplishment',
        'IPCR Target',
        'Workload',
        'Course Syllabus',
        'Course Syllabus Acceptance Form',
        'Exam',
        'TOS',
        'Class Record',
        'Grading Sheets',
        'Attendance Sheet',
        "Stakeholder's Feedback Form w/ Summary",
        'Consultation',
        'Lecture',
        'Activities',
        'CEIT-QF-03 Discussion Form',
        'Others'
    ];

    $faculty_conditions = ["u.role = 'user'", "u.is_approved = 1"];
    $faculty_params = [];

    if ($admin_department_id) {
        if (!empty($department_filter)) {
            // Check if the filtered department is allowed for this admin
            if ($hasParentId) {
                $stmt = $pdo->prepare("
                    SELECT id FROM departments 
                    WHERE (id = ? OR parent_id = ?) AND id = ? AND is_active = 1
                ");
                $stmt->execute([$admin_department_id, $admin_department_id, $department_filter]);
                $allowed_dept = $stmt->fetch();
                
                if ($allowed_dept) {
                    $faculty_conditions[] = "u.department_id = ?";
                    $faculty_params[] = $department_filter;
                } else {
                    // Restrict to admin's department and sub-departments
                    $faculty_conditions[] = "(u.department_id = ? OR d.parent_id = ?)";
                    $faculty_params[] = $admin_department_id;
                    $faculty_params[] = $admin_department_id;
                }
            } else {
                $stmt = $pdo->prepare("
                    SELECT id FROM departments 
                    WHERE id = ? AND id = ? AND is_active = 1
                ");
                $stmt->execute([$admin_department_id, $department_filter]);
                $allowed_dept = $stmt->fetch();
                
                if ($allowed_dept) {
                    $faculty_conditions[] = "u.department_id = ?";
                    $faculty_params[] = $department_filter;
                } else {
                    $faculty_conditions[] = "u.department_id = ?";
                    $faculty_params[] = $admin_department_id;
                }
            }
        } else {
            // Show admin's department and sub-departments
            if ($hasParentId) {
                $faculty_conditions[] = "(u.department_id = ? OR d.parent_id = ?)";
                $faculty_params[] = $admin_department_id;
                $faculty_params[] = $admin_department_id;
            } else {
                $faculty_conditions[] = "u.department_id = ?";
                $faculty_params[] = $admin_department_id;
            }
        }
    } else {
        if (!empty($department_filter)) {
            $faculty_conditions[] = "u.department_id = ?";
            $faculty_params[] = $department_filter;
        }
    }

    if (!empty($search)) {
        $faculty_conditions[] = "(CONCAT(u.name, ' ', u.surname) LIKE ? OR u.employee_id LIKE ? OR u.email LIKE ?)";
        $search_term = '%' . $search . '%';
        $faculty_params[] = $search_term;
        $faculty_params[] = $search_term;
        $faculty_params[] = $search_term;
    }

    $faculty_query = "
        SELECT u.id, u.name, u.mi, u.surname, u.employee_id, u.email, u.position,
            d.department_name, d.department_code, d.id as dept_id
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE " . implode(' AND ', $faculty_conditions) . "
        ORDER BY d.department_name, u.surname, u.name
    ";

    $stmt = $pdo->prepare($faculty_query);
    $stmt->execute($faculty_params);
    $faculty = $stmt->fetchAll();

    $file_submissions = [];
    if (!empty($faculty)) {
        $faculty_ids = array_column($faculty, 'id');
        $placeholders = implode(',', array_fill(0, count($faculty_ids), '?'));
        
        // UPDATED QUERY - Now compatible with user upload system
        $file_query = "
            SELECT 
                fds.faculty_id, 
                fds.document_type, 
                COUNT(df.id) as file_count,
                MAX(df.uploaded_at) as latest_upload,
                MIN(df.uploaded_at) as first_upload,
                SUM(df.file_size) as total_size,
                fds.academic_year,
                fds.semester
            FROM faculty_document_submissions fds
            LEFT JOIN document_files df ON fds.id = df.submission_id 
            WHERE fds.faculty_id IN ($placeholders)
            AND (df.id IS NOT NULL OR fds.id IS NOT NULL)
            GROUP BY fds.faculty_id, fds.document_type, fds.academic_year, fds.semester
        ";
        
        $stmt = $pdo->prepare($file_query);
        $stmt->execute($faculty_ids);
        
        while ($row = $stmt->fetch()) {
            // Create a unique key that includes academic period
            $key = $row['faculty_id'] . '_' . $row['academic_year'] . '_' . $row['semester'];
            
            $file_submissions[$row['faculty_id']][$row['document_type']] = [
                'file_count' => $row['file_count'] ?: 0,
                'latest_upload' => $row['latest_upload'],
                'first_upload' => $row['first_upload'],
                'total_size' => $row['total_size'] ?: 0,
                'status' => $row['file_count'] > 0 ? 'submitted' : 'pending',
                'academic_year' => $row['academic_year'],
                'semester' => $row['semester']
            ];
        }
    }

    // Apply status filter
    if (!empty($status_filter)) {
        $filtered_faculty = [];
        foreach ($faculty as $f) {
            $submitted_count = 0;
            foreach ($document_types as $dt) {
                if (isset($file_submissions[$f['id']][$dt])) {
                    $submitted_count++;
                }
            }
            
            $completion_percentage = count($document_types) > 0 ? ($submitted_count / count($document_types)) * 100 : 0;
            
            $include = false;
            switch ($status_filter) {
                case 'complete':
                    $include = $completion_percentage == 100;
                    break;
                case 'partial':
                    $include = $completion_percentage > 0 && $completion_percentage < 100;
                    break;
                case 'none':
                    $include = $completion_percentage == 0;
                    break;
                default:
                    $include = true;
            }
            
            if ($include) {
                $filtered_faculty[] = $f;
            }
        }
        $faculty = $filtered_faculty;
    }

    // Calculate statistics
    $total_faculty = count($faculty);
    $total_possible = $total_faculty * count($document_types);
    $submitted_count = 0;
    $complete_faculty = 0;

    foreach ($faculty as $f) {
        $faculty_submitted = 0;
        foreach ($document_types as $dt) {
            if (isset($file_submissions[$f['id']][$dt])) {
                $submitted_count++;
                $faculty_submitted++;
            }
        }
        if ($faculty_submitted == count($document_types)) {
            $complete_faculty++;
        }
    }

    $completion_rate = $total_possible > 0 ? round(($submitted_count / $total_possible) * 100, 1) : 0;
    $faculty_completion_rate = $total_faculty > 0 ? round(($complete_faculty / $total_faculty) * 100, 1) : 0;

    // Get departments for filter - handle parent_id conditionally
    $dept_conditions = ["is_active = 1"];
    $dept_params = [];

    if ($admin_department_id) {
        if ($hasParentId) {
            $dept_conditions[] = "(id = ? OR parent_id = ?)";
            $dept_params[] = $admin_department_id;
            $dept_params[] = $admin_department_id;
        } else {
            $dept_conditions[] = "id = ?";
            $dept_params[] = $admin_department_id;
        }
    }

    $dept_query = "SELECT * FROM departments WHERE " . implode(' AND ', $dept_conditions) . " ORDER BY department_name";
    $stmt = $pdo->prepare($dept_query);
    $stmt->execute($dept_params);
    $departments = $stmt->fetchAll();

    $semester_options = [
        '1st sem AY 2024-2025' => '1st Semester AY 2024-2025',
        '2nd sem AY 2024-2025' => '2nd Semester AY 2024-2025',
        '1st sem AY 2025-2026' => '1st Semester AY 2025-2026',
        '2nd sem AY 2025-2026' => '2nd Semester AY 2025-2026'
    ];

    function formatFileSize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            return $bytes . ' bytes';
        } elseif ($bytes == 1) {
            return '1 byte';
        } else {
            return '0 bytes';
        }
    }

    // Add semester/year filter support
    $selectedYear = $_GET['year'] ?? date('Y');
    $selectedSemester = $_GET['semester'] ?? '2nd sem AY 2024-2025';

    // Convert semester format for compatibility
    $semesterMap = [
        '2nd sem AY 2024-2025' => '2nd Semester',
        '1st sem AY 2024-2025' => '1st Semester',
        '2nd sem AY 2025-2026' => '2nd Semester',
        '1st sem AY 2025-2026' => '1st Semester'
    ];

    $normalizedSemester = $semesterMap[$selectedSemester] ?? '2nd Semester';

    // Extract year from semester string if not provided separately
    if (strpos($selectedSemester, 'AY') !== false) {
        preg_match('/AY (\d{4})-\d{4}/', $selectedSemester, $matches);
        if (!empty($matches[1])) {
            $selectedYear = (int)$matches[1];
        }
    }

    // Updated file query with year/semester filtering
    $file_submissions = [];
    if (!empty($faculty)) {
        $faculty_ids = array_column($faculty, 'id');
        $placeholders = implode(',', array_fill(0, count($faculty_ids), '?'));
        
        // Build the base query
        $file_query = "
            SELECT 
                fds.faculty_id, 
                fds.document_type, 
                COUNT(df.id) as file_count,
                MAX(df.uploaded_at) as latest_upload,
                MIN(df.uploaded_at) as first_upload,
                SUM(df.file_size) as total_size,
                fds.academic_year,
                fds.semester
            FROM faculty_document_submissions fds
            LEFT JOIN document_files df ON fds.id = df.submission_id 
            WHERE fds.faculty_id IN ($placeholders)
            AND fds.academic_year = ?
            AND fds.semester = ?
            GROUP BY fds.faculty_id, fds.document_type, fds.academic_year, fds.semester
        ";
        
        $stmt = $pdo->prepare($file_query);
        
        // Combine parameters: faculty_ids first, then year and semester
        $params = array_merge($faculty_ids, [$selectedYear, $normalizedSemester]);
        $stmt->execute($params);
        
        while ($row = $stmt->fetch()) {
            $file_submissions[$row['faculty_id']][$row['document_type']] = [
                'file_count' => $row['file_count'] ?: 0,
                'latest_upload' => $row['latest_upload'],
                'first_upload' => $row['first_upload'],
                'total_size' => $row['total_size'] ?: 0,
                'status' => $row['file_count'] > 0 ? 'submitted' : 'pending',
                'academic_year' => $row['academic_year'],
                'semester' => $row['semester']
            ];
        }
    }

?>