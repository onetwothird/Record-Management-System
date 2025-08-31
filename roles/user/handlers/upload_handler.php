<?php
// Enhanced upload_handler.php with proper academic year handling
require_once '../../../includes/config.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$currentUser = getCurrentUser($pdo);
if (!$currentUser || !$currentUser['is_approved']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'User not approved']);
    exit();
}

// Get user's department ID for security check
$userDepartmentId = null;
if (isset($currentUser['department_id']) && $currentUser['department_id']) {
    $userDepartmentId = $currentUser['department_id'];
} elseif (isset($currentUser['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
        $stmt->execute([$currentUser['id']]);
        $user = $stmt->fetch();
        if ($user && $user['department_id']) {
            $userDepartmentId = $user['department_id'];
        }
    } catch(Exception $e) {
        error_log("Department fetch error: " . $e->getMessage());
    }
}

if (!$userDepartmentId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No department assigned']);
    exit();
}

try {
    // Validate input - FIX: Check if files array exists and is not empty
    if (!isset($_POST['department']) || !isset($_POST['category']) || 
        !isset($_POST['semester']) || !isset($_POST['academic_year'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }

    // FIX: Proper file validation
    if (!isset($_FILES['files']) || !is_array($_FILES['files']['name']) || empty($_FILES['files']['name'][0])) {
        echo json_encode(['success' => false, 'message' => 'No files selected for upload']);
        exit();
    }

    $departmentId = (int)$_POST['department'];
    $category = $_POST['category'];
    $semester = $_POST['semester'];
    $academicYear = trim($_POST['academic_year']);
    $description = $_POST['description'] ?? '';
    
    // FIX: Proper tags handling
    $tags = [];
    if (isset($_POST['tags']) && !empty($_POST['tags'])) {
        $decodedTags = json_decode($_POST['tags'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedTags)) {
            $tags = $decodedTags;
        }
    }

    // Enhanced academic year validation
    if (empty($academicYear)) {
        echo json_encode(['success' => false, 'message' => 'Academic year is required']);
        exit();
    }

    // Validate academic year format (YYYY-YYYY)
    if (!preg_match('/^\d{4}-\d{4}$/', $academicYear)) {
        echo json_encode(['success' => false, 'message' => 'Invalid academic year format. Use YYYY-YYYY format.']);
        exit();
    }

    // Validate that the years are consecutive
    list($startYear, $endYear) = explode('-', $academicYear);
    if ((int)$endYear - (int)$startYear !== 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid academic year. End year must be exactly one year after start year.']);
        exit();
    }

    // SECURITY CHECK: User can only upload to their own department
    if ($departmentId != $userDepartmentId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied: Can only upload to your department']);
        exit();
    }
    
    // Validate department exists
    $stmt = $pdo->prepare("SELECT id, department_name FROM departments WHERE id = ? AND is_active = 1");
    $stmt->execute([$departmentId]);
    $department = $stmt->fetch();
    
    if (!$department) {
        echo json_encode(['success' => false, 'message' => 'Invalid department']);
        exit();
    }

    // Validate semester
    if (!in_array($semester, ['first', 'second'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid semester. Must be "first" or "second".']);
        exit();
    }

    // Validate category
    $validCategories = [
        'ipcr_accomplishment', 'ipcr_target', 'workload', 'course_syllabus',
        'syllabus_acceptance', 'exam', 'tos', 'class_record', 'grading_sheet',
        'attendance_sheet', 'stakeholder_feedback', 'consultation', 'lecture',
        'activities', 'exam_acknowledgement', 'consultation_log'
    ];
    
    if (!in_array($category, $validCategories)) {
        echo json_encode(['success' => false, 'message' => 'Invalid category']);
        exit();
    }

    // Get or create category folder with academic year
    $folderId = getOrCreateCategoryFolder($pdo, $departmentId, $category, $semester, $academicYear, $currentUser['id'], $userDepartmentId);
    if (!$folderId) {
        echo json_encode(['success' => false, 'message' => 'Failed to create category folder']);
        exit();
    }

    // Create upload directory with academic year in path
    $uploadDir = "../../uploads/departments/" . $departmentId . "/" . $category . "/" . $semester . "/" . $academicYear . "/";
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
            exit();
        }
    }

    $uploadedFiles = [];
    $errors = [];
    $fileCount = count($_FILES['files']['name']);

    // FIX: Better file processing loop with proper error handling
    for ($i = 0; $i < $fileCount; $i++) {
        // Skip empty file slots
        if (empty($_FILES['files']['name'][$i])) {
            continue;
        }

        $originalName = $_FILES['files']['name'][$i];
        $tmpName = $_FILES['files']['tmp_name'][$i];
        $fileSize = $_FILES['files']['size'][$i];
        $fileType = $_FILES['files']['type'][$i];
        $fileError = $_FILES['files']['error'][$i];

        // Check for upload errors
        if ($fileError !== UPLOAD_ERR_OK) {
            $uploadErrorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
                UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
                UPLOAD_ERR_PARTIAL => 'File partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'No temporary directory',
                UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
                UPLOAD_ERR_EXTENSION => 'Upload blocked by extension'
            ];
            
            $errorMessage = $uploadErrorMessages[$fileError] ?? 'Unknown upload error';
            $errors[] = "Upload error for: " . $originalName . " (" . $errorMessage . ")";
            continue;
        }

        // Validate file size (50MB max)
        if ($fileSize > 50 * 1024 * 1024) {
            $errors[] = "File too large: " . $originalName . " (Max 50MB)";
            continue;
        }
        
        // Get file extension
        $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        
        // Generate unique filename
        $fileName = uniqid() . '_' . time() . '_' . $i . '.' . $fileExtension;
        $filePath = $uploadDir . $fileName;
        
        // Move uploaded file
        if (move_uploaded_file($tmpName, $filePath)) {
            // Calculate file hash for duplicate detection
            $fileHash = hash_file('sha256', $filePath);
            
            // Check for duplicates - include academic year and semester in duplicate check
            $stmt = $pdo->prepare("
                SELECT f.id, f.original_name 
                FROM files f 
                INNER JOIN folders fo ON f.folder_id = fo.id
                WHERE f.file_hash = ? AND fo.department_id = ? AND fo.category = ? 
                AND f.academic_year = ? AND f.semester = ? AND f.is_deleted = 0
            ");
            $stmt->execute([$fileHash, $departmentId, $category, $academicYear, $semester]);
            $duplicate = $stmt->fetch();
            
            if ($duplicate) {
                unlink($filePath); // Remove the uploaded duplicate
                $errors[] = "Duplicate file: " . $originalName . " (already exists as " . $duplicate['original_name'] . " in " . $academicYear . " - " . ucfirst($semester) . " Semester)";
                continue;
            }
            
            // Detect MIME type properly
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMimeType = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            $mimeType = $detectedMimeType ?: $fileType;
            
            // Insert file record into database with academic year and semester
            $stmt = $pdo->prepare("
                INSERT INTO files (
                    file_name, original_name, file_path, file_size, file_type, 
                    mime_type, file_extension, uploaded_by, folder_id, 
                    file_hash, tags, description, academic_year, semester, 
                    uploaded_at, download_count
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0)
            ");
            
            $relativePath = "uploads/departments/" . $departmentId . "/" . $category . "/" . $semester . "/" . $academicYear . "/" . $fileName;
            $tagsJson = !empty($tags) ? json_encode($tags) : null;
            
            $result = $stmt->execute([
                $fileName,
                $originalName,
                $relativePath,
                $fileSize,
                $fileType,
                $mimeType,
                $fileExtension,
                $currentUser['id'],
                $folderId,
                $fileHash,
                $tagsJson,
                $description,
                $academicYear,
                $semester
            ]);

            if ($result) {
                $uploadedFiles[] = [
                    'id' => $pdo->lastInsertId(),
                    'name' => $originalName,
                    'size' => $fileSize,
                    'category' => $category,
                    'semester' => $semester,
                    'academic_year' => $academicYear
                ];

                // Update folder file count and size
                updateFolderStats($pdo, $folderId);
            } else {
                // If database insert failed, remove the uploaded file
                unlink($filePath);
                $errors[] = "Database error for: " . $originalName;
            }
            
        } else {
            $errors[] = "Failed to upload: " . $originalName;
        }
    }

    // FIX: Better response handling
    if (!empty($uploadedFiles)) {
        $semesterText = $semester === 'first' ? 'First' : 'Second';
        $categoryDisplayName = ucfirst(str_replace('_', ' ', $category));
        
        $response = [
            'success' => true,
            'message' => count($uploadedFiles) . " file" . (count($uploadedFiles) > 1 ? 's' : '') . " uploaded successfully to {$categoryDisplayName} - {$semesterText} Semester ({$academicYear})",
            'uploaded_files' => $uploadedFiles,
            'departmentId' => $departmentId,
            'category' => $category,
            'semester' => $semester,
            'academic_year' => $academicYear,
            'file_count' => count($uploadedFiles)
        ];
        
        if (!empty($errors)) {
            $response['warnings'] = $errors;
        }
        
        echo json_encode($response);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No files were uploaded successfully' . (!empty($errors) ? ': ' . implode(', ', $errors) : ''),
            'errors' => $errors
        ]);
    }

} catch (Exception $e) {
    error_log("Upload error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during upload: ' . $e->getMessage()
    ]);
}

// Helper function to update folder statistics
function updateFolderStats($pdo, $folderId) {
    try {
        $stmt = $pdo->prepare("
            UPDATE folders SET 
                file_count = (SELECT COUNT(*) FROM files WHERE folder_id = ? AND is_deleted = 0),
                folder_size = (SELECT COALESCE(SUM(file_size), 0) FROM files WHERE folder_id = ? AND is_deleted = 0),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$folderId, $folderId, $folderId]);
    } catch (Exception $e) {
        error_log("Error updating folder stats: " . $e->getMessage());
    }
}

// Enhanced helper function to get or create category folder with academic year
function getOrCreateCategoryFolder($pdo, $departmentId, $category, $semester, $academicYear, $userId, $userDepartmentId) {
    // Security check: ensure user can only create folders in their own department
    if ($departmentId != $userDepartmentId) {
        throw new Exception("Access denied: Cannot create folder in different department");
    }
    
    try {
        $semesterName = ($semester === 'first') ? 'First Semester' : 'Second Semester';
        $folderName = $academicYear . ' - ' . $semesterName;
        
        // Check if folder exists for this category, semester, and academic year
        $stmt = $pdo->prepare("
            SELECT id FROM folders 
            WHERE department_id = ? 
            AND folder_name = ? 
            AND category = ? 
            AND is_deleted = 0
        ");
        $stmt->execute([$departmentId, $folderName, $category]);
        $folder = $stmt->fetch();
        
        if ($folder) {
            return $folder['id'];
        }
        
        // Create new category folder
        $stmt = $pdo->prepare("
            INSERT INTO folders (
                folder_name, description, created_by, department_id, category, 
                folder_path, folder_level, created_at, file_count, folder_size
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 0, 0)
        ");
        
        $categoryNames = [
            'ipcr_accomplishment' => 'IPCR Accomplishment',
            'ipcr_target' => 'IPCR Target',
            'workload' => 'Workload',
            'course_syllabus' => 'Course Syllabus',
            'syllabus_acceptance' => 'Course Syllabus Acceptance Form',
            'exam' => 'Exam',
            'tos' => 'TOS',
            'class_record' => 'Class Record',
            'grading_sheet' => 'Grading Sheet',
            'attendance_sheet' => 'Attendance Sheet',
            'stakeholder_feedback' => 'Stakeholder\'s Feedback Form w/ Summary',
            'consultation' => 'Consultation',
            'lecture' => 'Lecture',
            'activities' => 'Activities',
            'exam_acknowledgement' => 'CEIT-QF-03 Discussion of Examination Acknowledgement Receipt Form',
            'consultation_log' => 'Consultation Log Sheet Form'
        ];
        
        $categoryDisplayName = $categoryNames[$category] ?? ucfirst(str_replace('_', ' ', $category));
        $description = "{$categoryDisplayName} files for {$semesterName} {$academicYear}";
        $folderPath = "/departments/{$departmentId}/{$category}/{$semester}/{$academicYear}";
        
        $stmt->execute([
            $folderName, 
            $description, 
            $userId, 
            $departmentId, 
            $category,
            $folderPath, 
            2
        ]);
        
        return $pdo->lastInsertId();
        
    } catch(Exception $e) {
        error_log("Error creating category folder: " . $e->getMessage());
        return false;
    }
}
?>