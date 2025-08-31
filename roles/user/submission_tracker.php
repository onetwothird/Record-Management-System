<?php
require_once '../../includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get current user information
$currentUser = getCurrentUser($pdo);

if (!$currentUser) {
    header('Location: logout.php');
    exit();
}

// Check if user is approved
if (!$currentUser['is_approved']) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=account_not_approved');
    exit();
}

// Get user's department ID
$userDepartmentId = null;

if (isset($currentUser['department_id']) && $currentUser['department_id']) {
    $userDepartmentId = $currentUser['department_id'];
} elseif (isset($currentUser['id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.department_id, d.department_code, d.department_name 
            FROM users u 
            LEFT JOIN departments d ON u.department_id = d.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$currentUser['id']]);
        $userDept = $stmt->fetch();
        
        if ($userDept && $userDept['department_id']) {
            $userDepartmentId = $userDept['department_id'];
            $currentUser['department_id'] = $userDept['department_id'];
            $currentUser['department_name'] = $userDept['department_name'];
        }
    } catch(Exception $e) {
        error_log("Department fetch error: " . $e->getMessage());
    }
}

if (!$userDepartmentId) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=no_department_assigned');
    exit();
}

// File categories with submission requirements
$fileCategories = [
    'ipcr_accomplishment' => [
        'name' => 'IPCR Accomplishment',
        'icon' => 'bxs-trophy',
        'color' => '#f59e0b',
        'deadline' => 'End of each semester',
        'required' => true,
        'frequency' => 'Per Semester'
    ],
    'ipcr_target' => [
        'name' => 'IPCR Target',
        'icon' => 'bxs-bullseye',
        'color' => '#ef4444',
        'deadline' => 'Beginning of each semester',
        'required' => true,
        'frequency' => 'Per Semester'
    ],
    'workload' => [
        'name' => 'Workload',
        'icon' => 'bxs-briefcase',
        'color' => '#8b5cf6',
        'deadline' => 'Start of academic year',
        'required' => true,
        'frequency' => 'Per Academic Year'
    ],
    'course_syllabus' => [
        'name' => 'Course Syllabus',
        'icon' => 'bxs-book-content',
        'color' => '#06b6d4',
        'deadline' => '2 weeks before classes',
        'required' => true,
        'frequency' => 'Per Subject'
    ],
    'syllabus_acceptance' => [
        'name' => 'Course Syllabus Acceptance Form',
        'icon' => 'bxs-check-circle',
        'color' => '#10b981',
        'deadline' => '1 week after class starts',
        'required' => true,
        'frequency' => 'Per Subject'
    ],
    'exam' => [
        'name' => 'Exam',
        'icon' => 'bxs-file-doc',
        'color' => '#dc2626',
        'deadline' => '1 week before exam',
        'required' => true,
        'frequency' => 'Per Exam Period'
    ],
    'tos' => [
        'name' => 'TOS',
        'icon' => 'bxs-spreadsheet',
        'color' => '#059669',
        'deadline' => 'With exam submission',
        'required' => true,
        'frequency' => 'Per Exam Period'
    ],
    'class_record' => [
        'name' => 'Class Record',
        'icon' => 'bxs-data',
        'color' => '#7c3aed',
        'deadline' => 'End of semester',
        'required' => true,
        'frequency' => 'Per Subject'
    ],
    'grading_sheet' => [
        'name' => 'Grading Sheet',
        'icon' => 'bxs-calculator',
        'color' => '#ea580c',
        'deadline' => '3 days after final exam',
        'required' => true,
        'frequency' => 'Per Subject'
    ],
    'attendance_sheet' => [
        'name' => 'Attendance Sheet',
        'icon' => 'bxs-user-check',
        'color' => '#0284c7',
        'deadline' => 'End of semester',
        'required' => true,
        'frequency' => 'Per Subject'
    ],
    'stakeholder_feedback' => [
        'name' => 'Stakeholder\'s Feedback Form w/ Summary',
        'icon' => 'bxs-comment-detail',
        'color' => '#9333ea',
        'deadline' => 'Mid and end of semester',
        'required' => false,
        'frequency' => 'Twice per Semester'
    ],
    'consultation' => [
        'name' => 'Consultation',
        'icon' => 'bxs-chat',
        'color' => '#0d9488',
        'deadline' => 'As scheduled',
        'required' => false,
        'frequency' => 'As Needed'
    ],
    'lecture' => [
        'name' => 'Lecture',
        'icon' => 'bxs-chalkboard',
        'color' => '#7c2d12',
        'deadline' => 'Before each class',
        'required' => false,
        'frequency' => 'Per Class'
    ],
    'activities' => [
        'name' => 'Activities',
        'icon' => 'bxs-game',
        'color' => '#be185d',
        'deadline' => 'Before activity date',
        'required' => false,
        'frequency' => 'Per Activity'
    ],
    'exam_acknowledgement' => [
        'name' => 'CEIT-QF-03 Discussion of Examination Acknowledgement Receipt Form',
        'icon' => 'bxs-receipt',
        'color' => '#1e40af',
        'deadline' => 'After exam discussion',
        'required' => true,
        'frequency' => 'Per Exam Period'
    ],
    'consultation_log' => [
        'name' => 'Consultation Log Sheet Form',
        'icon' => 'bxs-notepad',
        'color' => '#374151',
        'deadline' => 'End of semester',
        'required' => true,
        'frequency' => 'Per Semester'
    ]
];

// Get user's department
function getUserDepartment($pdo, $departmentId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ? AND is_active = 1");
        $stmt->execute([$departmentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        error_log("Error fetching user department: " . $e->getMessage());
        return null;
    }
}

// Get submission statistics for user's department
function getSubmissionStats($pdo, $userId, $userDepartmentId, $category = null, $semester = null) {
    try {
        $whereClause = "WHERE f.department_id = ? AND fi.uploaded_by = ?";
        $params = [$userDepartmentId, $userId];
        
        if ($category) {
            $whereClause .= " AND f.category = ?";
            $params[] = $category;
        }
        
        if ($semester) {
            $semesterName = ($semester === 'first') ? 'First Semester' : 'Second Semester';
            $whereClause .= " AND f.folder_name LIKE ?";
            $params[] = "%{$semesterName}%";
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                f.category,
                COUNT(fi.id) as file_count,
                MAX(fi.uploaded_at) as last_submission,
                f.folder_name
            FROM folders f
            LEFT JOIN files fi ON f.id = fi.folder_id
            {$whereClause}
            AND f.is_deleted = 0 
            AND (fi.is_deleted = 0 OR fi.is_deleted IS NULL)
            GROUP BY f.category, f.folder_name
            ORDER BY last_submission DESC
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        error_log("Error fetching submission stats: " . $e->getMessage());
        return [];
    }
}

function getAvailableAcademicYears($pdo, $userDepartmentId) {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT 
                SUBSTRING_INDEX(f.folder_name, ' - ', 1) as academic_year
            FROM folders f
            WHERE f.department_id = ? 
            AND f.is_deleted = 0
            AND f.folder_name REGEXP '^[0-9]{4}-[0-9]{4}'
            ORDER BY academic_year DESC
        ");
        
        $stmt->execute([$userDepartmentId]);
        $years = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // If no years found in database, generate current and next academic year
        if (empty($years)) {
            $currentYear = date('Y');
            $nextYear = $currentYear + 1;
            $years = [
                $currentYear . '-' . $nextYear,
                ($currentYear - 1) . '-' . $currentYear
            ];
        }
        
        return $years;
    } catch(Exception $e) {
        error_log("Error fetching academic years: " . $e->getMessage());
        // Fallback to current academic year
        $currentYear = date('Y');
        $nextYear = $currentYear + 1;
        return [$currentYear . '-' . $nextYear];
    }
}

// Add this line after your existing data fetching
$availableAcademicYears = getAvailableAcademicYears($pdo, $userDepartmentId);


// Get recent submissions
function getRecentSubmissions($pdo, $userId, $userDepartmentId, $limit = 10) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                fi.file_name,
                fi.uploaded_at,
                f.category,
                f.folder_name,
                fi.file_size,
                fi.file_extension
            FROM files fi
            JOIN folders f ON fi.folder_id = f.id
            WHERE f.department_id = ? 
            AND fi.uploaded_by = ?
            AND fi.is_deleted = 0 
            AND f.is_deleted = 0
            ORDER BY fi.uploaded_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$userDepartmentId, $userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        error_log("Error fetching recent submissions: " . $e->getMessage());
        return [];
    }
}

// Get pending submissions
function getPendingSubmissions($fileCategories, $submissionStats) {
    $pending = [];
    $currentAcademicYear = date('Y') . '-' . (date('Y') + 1);
    $currentSemester = (date('n') >= 8 || date('n') <= 12) ? 'first' : 'second';
    
    foreach ($fileCategories as $key => $category) {
        if ($category['required']) {
            $hasFirstSemester = false;
            $hasSecondSemester = false;
            
            foreach ($submissionStats as $stat) {
                if ($stat['category'] === $key) {
                    if (strpos($stat['folder_name'], 'First Semester') !== false) {
                        $hasFirstSemester = true;
                    }
                    if (strpos($stat['folder_name'], 'Second Semester') !== false) {
                        $hasSecondSemester = true;
                    }
                }
            }
            
            if (!$hasFirstSemester) {
                $pending[] = [
                    'category' => $key,
                    'name' => $category['name'],
                    'semester' => 'First Semester',
                    'deadline' => $category['deadline'],
                    'priority' => 'high'
                ];
            }
            
            if (!$hasSecondSemester) {
                $pending[] = [
                    'category' => $key,
                    'name' => $category['name'],
                    'semester' => 'Second Semester',
                    'deadline' => $category['deadline'],
                    'priority' => 'high'
                ];
            }
        }
    }
    
    return $pending;
}

// Format file size
function formatFileSize($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Get file icon based on extension
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $iconMap = [
        'pdf' => 'bxs-file-pdf',
        'doc' => 'bxs-file-doc',
        'docx' => 'bxs-file-doc',
        'xls' => 'bxs-spreadsheet',
        'xlsx' => 'bxs-spreadsheet',
        'ppt' => 'bxs-file-blank',
        'pptx' => 'bxs-file-blank',
        'jpg' => 'bxs-file-image',
        'jpeg' => 'bxs-file-image',
        'png' => 'bxs-file-image',
        'gif' => 'bxs-file-image',
        'txt' => 'bxs-file-txt',
        'zip' => 'bxs-file-archive',
        'rar' => 'bxs-file-archive',
        'mp4' => 'bxs-videos',
        'avi' => 'bxs-videos',
        'mp3' => 'bxs-music',
        'wav' => 'bxs-music'
    ];
    
    return isset($iconMap[$ext]) ? $iconMap[$ext] : 'bxs-file';
}

// Get user initials
function getUserInitials($fullName) {
    $names = explode(' ', $fullName);
    $initials = '';
    foreach($names as $name) {
        if(!empty($name)) {
            $initials .= strtoupper($name[0]);
        }
    }
    return substr($initials, 0, 2);
}

$userDepartment = getUserDepartment($pdo, $userDepartmentId);
$submissionStats = getSubmissionStats($pdo, $currentUser['id'], $userDepartmentId);
$recentSubmissions = getRecentSubmissions($pdo, $currentUser['id'], $userDepartmentId);
$pendingSubmissions = getPendingSubmissions($fileCategories, $submissionStats);

// Department configuration
$departmentConfig = [
    'TED' => ['icon' => 'bxs-graduation', 'color' => '#f59e0b'],
    'MD' => ['icon' => 'bxs-business', 'color' => '#1e40af'],
    'FASD' => ['icon' => 'bx bx-water', 'color' => '#0284c7'],
    'ASD' => ['icon' => 'bxs-palette', 'color' => '#d946ef'],
    'ITD' => ['icon' => 'bxs-chip', 'color' => '#0f766e'],
    'NSTP' => ['icon' => 'bxs-user-check', 'color' => '#22c55e'],
    'OTHR' => ['icon' => 'bxs-file', 'color' => '#6b7280']
];

$departmentImage = null;
$departmentCode = null;

if ($userDepartment) {
    $departmentCode = $userDepartment['department_code'];
    $departmentImage = "../../img/{$departmentCode}.jpg";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submission Tracker - CVSU Naic</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/components/navbar.css">
    <link rel="stylesheet" href="assets/css/components/sidebar.css">
    <link rel="stylesheet" href="assets/css/pages/submission_tracker/academic-stats.css">
    <link rel="stylesheet" href="assets/css/pages/submission_tracker/activity.css">
    <link rel="stylesheet" href="assets/css/pages/submission_tracker/categories.css">
    <link rel="stylesheet" href="assets/css/pages/submission_tracker/control_system.css">
    <link rel="stylesheet" href="assets/css/pages/submission_tracker/filter.css">
    <link rel="stylesheet" href="assets/css/pages/submission_tracker/profile_system.css">
    <link rel="stylesheet" href="assets/css/pages/submission_tracker/responsive.css">
    <link rel="stylesheet" href="assets/css/pages/submission_tracker/search-filter.css">
    <link rel="stylesheet" href="assets/css/pages/submission_tracker/submission.css">
    <link rel="stylesheet" href="assets/css/pages/submission_tracker/summary.css">
    <style>
     .profile::after {
            content: '<?php echo $departmentCode; ?>';
            position: absolute;
            bottom: -2px;
            right: -2px;
            background: var(--blue);
            color: white;
            font-size: 8px;
            padding: 2px 4px;
            border-radius: 4px;
            font-weight: 500;
            min-width: 20px;
            text-align: center;
     }
    </style>
</head>
<body>
    <?php include 'components/sidebar.html'; ?>

    <!-- Content -->
    <section id="content">
        <?php include 'components/navbar.html'; ?>

        <main>
            <div class="head-title">
                <div class="left">
                    <h1>My Tracker</h1>
                    <ul class="breadcrumb">
                        <li>
                            <a href="dashboard.php">Dashboard</a>
                        </li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li>
                        <a class="" href="#" style="color: #28a745;">My Tracker</a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="stats-overview">
                <div class="stat-card completed">
                    <div class="stat-icon">
                        <i class='bx bxs-check-circle'></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo count($submissionStats); ?></div>
                        <div class="stat-label">Completed Submissions</div>
                        <div class="stat-trend positive">
                            <i class='bx bx-trending-up'></i>
                            <span>This semester</span>
                        </div>
                    </div>
                </div>

                <div class="stat-card pending">
                    <div class="stat-icon">
                        <i class='bx bxs-time-five'></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo count($pendingSubmissions); ?></div>
                        <div class="stat-label">Pending Submissions</div>
                        <div class="stat-trend <?php echo count($pendingSubmissions) > 0 ? 'negative' : 'neutral'; ?>">
                            <i class='bx <?php echo count($pendingSubmissions) > 0 ? 'bx-trending-down' : 'bx-minus'; ?>'></i>
                            <span>Required files</span>
                        </div>
                    </div>
                </div>

                <div class="stat-card total">
                    <div class="stat-icon">
                        <i class='bx bxs-folder'></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo array_sum(array_column($submissionStats, 'file_count')); ?></div>
                        <div class="stat-label">Total Files Uploaded</div>
                        <div class="stat-trend positive">
                            <i class='bx bx-trending-up'></i>
                            <span>All time</span>
                        </div>
                    </div>
                </div>

                <div class="stat-card progress">
                    <div class="stat-icon">
                        <i class='bx bxs-pie-chart-alt-2'></i>
                    </div>
                    <div class="stat-info">
                        <?php 
                        $requiredCategories = array_filter($fileCategories, function($cat) { return $cat['required']; });
                        $completedRequired = 0;
                        foreach ($requiredCategories as $key => $cat) {
                            foreach ($submissionStats as $stat) {
                                if ($stat['category'] === $key && $stat['file_count'] > 0) {
                                    $completedRequired++;
                                    break;
                                }
                            }
                        }
                        $progressPercentage = count($requiredCategories) > 0 ? round(($completedRequired / count($requiredCategories)) * 100) : 100;
                        ?>
                        <div class="stat-number"><?php echo $progressPercentage; ?>%</div>
                        <div class="stat-label">Completion Rate</div>
                        <div class="stat-trend <?php echo $progressPercentage >= 80 ? 'positive' : ($progressPercentage >= 50 ? 'neutral' : 'negative'); ?>">
                            <i class='bx <?php echo $progressPercentage >= 80 ? 'bx-trending-up' : ($progressPercentage >= 50 ? 'bx-minus' : 'bx-trending-down'); ?>'></i>
                            <span>Required submissions</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-tabs">
                    <button class="filter-tab active" onclick="showSection('overview')">
                        <i class='bx bx-chart'></i> Overview
                    </button>
                    <button class="filter-tab" onclick="showSection('pending')">
                        <i class='bx bx-time-five'></i> Pending
                        <?php if (count($pendingSubmissions) > 0): ?>
                            <span class="badge"><?php echo count($pendingSubmissions); ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="filter-tab" onclick="showSection('completed')">
                        <i class='bx bx-check-circle'></i> Completed
                    </button>
                   
                </div>
                <div class="filter-controls">
                    <div class="search-box">
                        <i class='bx bx-search'></i>
                        <input type="text" placeholder="Search submissions..." id="submissionSearch">
                    </div>
                    <select id="categoryFilter" class="filter-select">
                        <option value="">All Categories</option>
                        <?php foreach ($fileCategories as $key => $category): ?>
                            <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="semesterFilter" class="filter-select">
                        <option value="">All Semesters</option>
                        <option value="first">First Semester</option>
                        <option value="second">Second Semester</option>
                    </select>
                     <select id="academicYearFilter" class="filter-select">
                        <option value="">All Academic Years</option>
                        <?php foreach ($availableAcademicYears as $year): ?>
                            <option value="<?php echo htmlspecialchars($year); ?>"><?php echo htmlspecialchars($year); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Content Sections -->
            <div id="overview-section" class="content-section active">
                <div class="section-grid">
                    <!-- Submission Categories Grid -->
                    <div class="categories-overview">
                        <div class="section-header">
                            <h3><i class='bx bxs-grid'></i> Submission Categories</h3>
                            <p>Track your progress across different file categories</p>
                        </div>
                        
                        <div class="categories-grid">
                            <?php foreach ($fileCategories as $key => $category): ?>
                                <?php
                                $categoryStats = array_filter($submissionStats, function($stat) use ($key) {
                                    return $stat['category'] === $key;
                                });
                                $totalFiles = array_sum(array_column($categoryStats, 'file_count'));
                                $lastSubmission = null;
                                if (!empty($categoryStats)) {
                                    $lastSubmission = max(array_column($categoryStats, 'last_submission'));
                                }
                                ?>
                                <div class="category-overview-card" data-category="<?php echo $key; ?>">
                                    <div class="category-header">
                                        <div class="category-icon" style="background-color: <?php echo $category['color']; ?>">
                                            <i class='bx <?php echo $category['icon']; ?>'></i>
                                        </div>
                                        <div class="category-status">
                                            <?php if ($totalFiles > 0): ?>
                                                <div class="status-badge completed">
                                                    <i class='bx bx-check'></i>
                                                </div>
                                            <?php elseif ($category['required']): ?>
                                                <div class="status-badge pending">
                                                    <i class='bx bx-time'></i>
                                                </div>
                                            <?php else: ?>
                                                <div class="status-badge optional">
                                                    <i class='bx bx-minus'></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="category-info">
                                        <h4><?php echo htmlspecialchars($category['name']); ?></h4>
                                        <div class="category-meta">
                                            <div class="meta-item">
                                                <i class='bx bx-file'></i>
                                                <span><?php echo $totalFiles; ?> files</span>
                                            </div>
                                            <div class="meta-item">
                                                <i class='bx bx-calendar'></i>
                                                <span><?php echo $category['frequency']; ?></span>
                                            </div>
                                            <?php if ($category['required']): ?>
                                                <div class="meta-item required">
                                                    <i class='bx bx-star'></i>
                                                    <span>Required</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="deadline-info">
                                            <i class='bx bx-time-five'></i>
                                            <span><?php echo $category['deadline']; ?></span>
                                        </div>
                                        
                                        <?php if ($lastSubmission): ?>
                                            <div class="last-submission">
                                                <i class='bx bx-check-circle'></i>
                                                <span>Last: <?php echo date('M j, Y', strtotime($lastSubmission)); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div id="pending-section" class="content-section">
                <div class="section-header">
                    <h3><i class='bx bxs-time-five'></i> Pending Submissions</h3>
                    <p>Required submissions that need your attention</p>
                </div>
                
                <div class="pending-grid">
                    <?php if (empty($pendingSubmissions)): ?>
                        <div class="empty-state">
                            <i class='bx bx-check-double empty-icon'></i>
                            <h4>All caught up! 🎉</h4>
                            <p>You have no pending required submissions at this time.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pendingSubmissions as $pending): ?>
                            <div class="pending-card <?php echo $pending['priority']; ?>">
                                <div class="pending-header">
                                    <div class="pending-icon" style="background-color: <?php echo $fileCategories[$pending['category']]['color']; ?>">
                                        <i class='bx <?php echo $fileCategories[$pending['category']]['icon']; ?>'></i>
                                    </div>
                                    <div class="priority-badge <?php echo $pending['priority']; ?>">
                                        <i class='bx bx-error'></i>
                                        <?php echo ucfirst($pending['priority']); ?> Priority
                                    </div>
                                </div>
                                
                                <div class="pending-info">
                                    <h4><?php echo htmlspecialchars($pending['name']); ?></h4>
                                    <div class="pending-details">
                                        <div class="detail-item">
                                            <i class='bx bx-calendar'></i>
                                            <span><?php echo $pending['semester']; ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <i class='bx bx-time-five'></i>
                                            <span><?php echo $pending['deadline']; ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="pending-actions">
                                    <button class="btn-upload" onclick="uploadForCategory('<?php echo $pending['category']; ?>', '<?php echo strtolower(str_replace(' ', '', $pending['semester'])); ?>')">
                                        <i class='bx bx-upload'></i>
                                        Upload Now
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div id="completed-section" class="content-section">
                <div class="section-header">
                    <h3><i class='bx bxs-check-circle'></i> Completed Submissions</h3>
                    <p>Your successfully submitted files organized by category</p>
                </div>
                
                <div class="completed-grid">
                    <?php if (empty($submissionStats)): ?>
                        <div class="empty-state">
                            <i class='bx bx-folder-open empty-icon'></i>
                            <h4>No submissions yet</h4>
                            <p>Your completed submissions will appear here once you start uploading files.</p>
                        </div>
                    <?php else: ?>
                        <?php 
                        $groupedStats = [];
                        foreach ($submissionStats as $stat) {
                            if (!isset($groupedStats[$stat['category']])) {
                                $groupedStats[$stat['category']] = [];
                            }
                            $groupedStats[$stat['category']][] = $stat;
                        }
                        ?>
                        <?php foreach ($groupedStats as $categoryKey => $categoryStats): ?>
                            <?php if (isset($fileCategories[$categoryKey])): ?>
                                <div class="completed-card">
                                    <div class="completed-header">
                                        <div class="completed-icon" style="background-color: <?php echo $fileCategories[$categoryKey]['color']; ?>">
                                            <i class='bx <?php echo $fileCategories[$categoryKey]['icon']; ?>'></i>
                                        </div>
                                        <div class="completed-info">
                                            <h4><?php echo htmlspecialchars($fileCategories[$categoryKey]['name']); ?></h4>
                                            <div class="completed-count">
                                                <?php echo array_sum(array_column($categoryStats, 'file_count')); ?> files submitted
                                            </div>
                                        </div>
                                        <div class="completed-badge">
                                            <i class='bx bx-check'></i>
                                            Complete
                                        </div>
                                    </div>
                                    
                                    <div class="semester-breakdown">
                                        <?php foreach ($categoryStats as $stat): ?>
                                            <div class="semester-item">
                                                <div class="semester-info">
                                                    <i class='bx bx-calendar'></i>
                                                    <span><?php echo htmlspecialchars($stat['folder_name']); ?></span>
                                                </div>
                                                <div class="semester-stats">
                                                    <span class="file-count"><?php echo $stat['file_count']; ?> files</span>
                                                    <span class="submission-date"><?php echo date('M j, Y', strtotime($stat['last_submission'])); ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </section>

    <script>
        window.userDepartmentId = <?php echo json_encode($userDepartmentId); ?>;
        window.fileCategories = <?php echo json_encode($fileCategories); ?>;
        window.submissionStats = <?php echo json_encode($submissionStats); ?>;
    </script>
    <script src="assets/js/pages/submission_tracker.js"></script>
    <script src="assets/js/components/navbar.js"></script>
</body>
</html>