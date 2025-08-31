<?php
require_once '../../includes/config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$currentUser = getCurrentUser($pdo);

if (!$currentUser) {
    header('Location: logout.php');
    exit();
}

// Ensure all expected fields exist with default values
$currentUser = array_merge([
    'name' => '',
    'mi' => '',
    'surname' => '',
    'email' => '',
    'employee_id' => '',
    'position' => '',
    'phone' => '',
    'address' => '',
    'date_of_birth' => null,
    'hire_date' => null,
    'profile_image' => null,
    'department_id' => null,
    'department_name' => ''
], $currentUser);

if (!$currentUser['is_approved']) {
    session_unset();
    session_destroy();
    header('Location: login.php?error=account_not_approved');
    exit();
}

$success_message = '';
$error_message   = '';

$departmentImage = null;
$departmentCode  = null;

// Default profile image path - same as navbar.html
$defaultProfileImage = '../../img/default-avatar.png';

// Profile image handling - simplified to match navbar.html approach
$profileImage = $defaultProfileImage;

if (!empty($currentUser['profile_image'])) {
    // Database stores relative path like: uploads/profile_images/profile_28_1755476391.png
    $dbImagePath = $currentUser['profile_image'];
    
    // For web display, we need to add the ../../ prefix to go up from current directory
    $webImagePath = '../../' . ltrim($dbImagePath, '/');
    
    // For file_exists check, we need the actual file system path
    $fileSystemPath = '../../' . ltrim($dbImagePath, '/');
    
    if (file_exists($fileSystemPath)) {
        // Add cache busting parameter using file modification time
        $profileImage = $webImagePath . '?v=' . filemtime($fileSystemPath);
    } else {
        // Log missing file but don't show error to user
        error_log("Profile image not found at: " . $fileSystemPath);
        // Keep default image
    }
}

// Get department information
if (!empty($currentUser['department_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT department_code, department_name FROM departments WHERE id = ?");
        $stmt->execute([$currentUser['department_id']]);
        $department = $stmt->fetch();

        if ($department) {
            $departmentCode = $department['department_code'];
            $departmentImage = "../../img/{$departmentCode}.jpg";
            $currentUser['department_name'] = $department['department_name'] ?? null;
        }
    } catch (Exception $e) {
        error_log("Department fetch error: " . $e->getMessage());
    }
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
            $currentUser['department_id']   = $userDept['department_id'];
            $currentUser['department_name'] = $userDept['department_name'];
            $departmentCode                 = $userDept['department_code'];
            $departmentImage                 = "../../img/{$departmentCode}.jpg";
        }
    } catch (Exception $e) {
        error_log("Department fetch error: " . $e->getMessage());
    }
}

// Function to delete old profile image
function deleteOldProfileImage($oldImagePath) {
    if (!empty($oldImagePath)) {
        $fullPath = '../../' . ltrim($oldImagePath, '/');
        
        if (file_exists($fullPath) && $fullPath !== '../../img/default-avatar.png') {
            if (unlink($fullPath)) {
                return "Old image deleted: " . $fullPath;
            } else {
                return "Failed to delete old image: " . $fullPath;
            }
        }
    }
    return "No old image to delete";
}

// Function to handle profile image upload
function handleProfileImageUpload($userId, $oldImagePath = null) {
    if (!isset($_FILES['profile_picture'])) {
        return null;
    }
    
    $file = $_FILES['profile_picture'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_NO_FILE:
                return null;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new Exception('File too large. Maximum size is 5MB.');
            case UPLOAD_ERR_PARTIAL:
                throw new Exception('File upload was interrupted.');
            case UPLOAD_ERR_NO_TMP_DIR:
                throw new Exception('Missing temporary folder.');
            case UPLOAD_ERR_CANT_WRITE:
                throw new Exception('Failed to write file to disk.');
            case UPLOAD_ERR_EXTENSION:
                throw new Exception('File upload blocked by server extension.');
            default:
                throw new Exception('Unknown upload error occurred.');
        }
    }

    // Enhanced file validation with WEBP support and 5MB limit
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    // Enhanced MIME type validation using finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    // Validate file type using both detected and reported MIME types
    if (!in_array($detectedMimeType, $allowedTypes) && !in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.');
    }

    // Validate file extension
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($fileExtension, $allowedExtensions)) {
        throw new Exception('Invalid file extension. Only jpg, jpeg, png, gif, and webp are allowed.');
    }

    // Validate file size (5MB)
    if ($file['size'] > $maxSize) {
        throw new Exception('File too large. Maximum size is 5MB.');
    }

    // Create upload directory if it doesn't exist
    $uploadDir = '../../uploads/profile_images/';
    
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create upload directory.');
        }
    }

    // Check if directory is writable
    if (!is_writable($uploadDir)) {
        throw new Exception('Upload directory is not writable.');
    }

    // Delete old profile image before uploading new one
    if ($oldImagePath) {
        deleteOldProfileImage($oldImagePath);
    }

    // Generate unique filename with timestamp
    $fileName = 'profile_' . $userId . '_' . time() . '.' . $fileExtension;
    $uploadPath = $uploadDir . $fileName;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // Verify file was actually moved
        if (file_exists($uploadPath)) {
            // Return relative path for database storage
            $relativePath = 'uploads/profile_images/' . $fileName;
            return $relativePath;
        } else {
            throw new Exception('File was moved but cannot be found.');
        }
    } else {
        throw new Exception('Failed to upload file.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        try {
            $profileImagePath = null;
            
            // Handle profile image upload if provided
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
                $oldImagePath = isset($currentUser['profile_image']) ? $currentUser['profile_image'] : null;
                $profileImagePath = handleProfileImageUpload($currentUser['id'], $oldImagePath);
            }
            
            // Sanitize and validate input data
            $name = trim($_POST['name'] ?? $currentUser['name']);
            $mi = trim($_POST['mi'] ?? $currentUser['mi']);
            $surname = trim($_POST['surname'] ?? $currentUser['surname']);
            $employee_id = trim($_POST['employee_id'] ?? $currentUser['employee_id']);
            $position = trim($_POST['position'] ?? $currentUser['position']);
            $phone = trim($_POST['phone'] ?? $currentUser['phone']);
            $address = trim($_POST['address'] ?? $currentUser['address']);
            $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : $currentUser['date_of_birth'];
            
            // Validate required fields
            if (empty($name) || empty($surname)) {
                throw new Exception('First name and last name are required.');
            }
            
            // Validate phone number format (optional)
            if (!empty($phone) && !preg_match('/^[\d\s\-\+\(\)]+$/', $phone)) {
                throw new Exception('Please enter a valid phone number.');
            }
            
            // Validate date of birth (optional)
            if (!empty($date_of_birth)) {
                $birthDate = new DateTime($date_of_birth);
                $today = new DateTime();
                $age = $today->diff($birthDate)->y;
                if ($age > 120 || $age < 16) {
                    throw new Exception('Please enter a valid date of birth.');
                }
            }
            
            // Prepare SQL based on whether image was uploaded
            if ($profileImagePath) {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, mi = ?, surname = ?, employee_id = ?, position = ?, phone = ?, address = ?, date_of_birth = ?, profile_image = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $result = $stmt->execute([
                    $name, $mi, $surname, $employee_id, $position, $phone, $address, $date_of_birth, $profileImagePath, $currentUser['id']
                ]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, mi = ?, surname = ?, employee_id = ?, position = ?, phone = ?, address = ?, date_of_birth = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $result = $stmt->execute([
                    $name, $mi, $surname, $employee_id, $position, $phone, $address, $date_of_birth, $currentUser['id']
                ]);
            }
            
            $success_message = 'Profile updated successfully!';
            
            // Refresh current user data from database
            $currentUser = getCurrentUser($pdo);
            
            // Update profile image for display with fresh data - same logic as navbar
            if (!empty($currentUser['profile_image'])) {
                $dbImagePath = $currentUser['profile_image'];
                $webImagePath = '../../' . ltrim($dbImagePath, '/');
                $fileSystemPath = '../../' . ltrim($dbImagePath, '/');
                
                if (file_exists($fileSystemPath)) {
                    $profileImage = $webImagePath . '?v=' . filemtime($fileSystemPath);
                }
            } else {
                $profileImage = $defaultProfileImage;
            }
        } catch (Exception $e) {
            $error_message = 'Error updating profile: ' . $e->getMessage();
        }
    }

    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validate password requirements
        if (strlen($newPassword) < 8) {
            $error_message = 'New password must be at least 8 characters long!';
        } elseif ($newPassword !== $confirmPassword) {
            $error_message = 'New passwords do not match!';
        } elseif (!password_verify($currentPassword, $currentUser['password'])) {
            $error_message = 'Current password is incorrect!';
        } else {
            try {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$hashedPassword, $currentUser['id']]);
                $success_message = 'Password changed successfully!';
                
                // Log password change for security
                error_log("Password changed for user ID: " . $currentUser['id'] . " at " . date('Y-m-d H:i:s'));
            } catch (Exception $e) {
                $error_message = 'Error changing password: ' . $e->getMessage();
                error_log("Password change error for user ID " . $currentUser['id'] . ": " . $e->getMessage());
            }
        }
    }

    if (isset($_POST['remove_profile_image'])) {
        try {
            // Delete the current profile image file
            $oldImagePath = isset($currentUser['profile_image']) ? $currentUser['profile_image'] : null;
            deleteOldProfileImage($oldImagePath);
            
            // Update database to remove profile image reference
            $stmt = $pdo->prepare("UPDATE users SET profile_image = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$currentUser['id']]);
            
            $success_message = 'Profile image removed successfully!';
            $currentUser = getCurrentUser($pdo);
            $profileImage = $defaultProfileImage;
        } catch (Exception $e) {
            $error_message = 'Error removing profile image: ' . $e->getMessage();
        }
    }
}

// Build full name for display
$fullName = trim($currentUser['name'] . ' ' . (!empty($currentUser['mi']) ? $currentUser['mi'] . '. ' : '') . $currentUser['surname']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - CVSU Naic</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/components/navbar.css">
    <link rel="stylesheet" href="assets/css/components/sidebar.css">
    <link rel="stylesheet" href="assets/css/pages/settings/alert_system.css">
    <link rel="stylesheet" href="assets/css/pages/settings/button_system.css">
    <link rel="stylesheet" href="assets/css/pages/settings/dark_theme.css">
    <link rel="stylesheet" href="assets/css/pages/settings/form_components.css">
    <link rel="stylesheet" href="assets/css/pages/settings/tab_navigation.css">
    <link rel="stylesheet" href="assets/css/pages/settings/settings_animation.css">
    <link rel="stylesheet" href="assets/css/pages/settings/profile_system.css">
    <link rel="stylesheet" href="assets/css/pages/settings/settings.css">
</head>
<body>

    <?php include 'components/sidebar.html'; ?>

    <section id="content">
        <?php include 'components/navbar.html'; ?>

        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Settings</h1>
                    <ul class="breadcrumb">
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="">Settings</a></li>
                    </ul>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success" id="successAlert">
                    <i class='bx bx-check-circle'></i>
                    <span><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error" id="errorAlert">
                    <i class='bx bx-error-circle'></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <div class="settings-container">
                <!-- Settings Tabs -->
                <div class="settings-tabs">
                    <button class="tab-btn active" onclick="showTab('profile')">
                        <i class='bx bx-user'></i> Profile
                    </button>
                    <button class="tab-btn" onclick="showTab('security')">
                        <i class='bx bx-shield'></i> Security
                    </button>
                </div>

                <!-- Profile Settings Tab -->
                <div id="profile" class="tab-content active">
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class='bx bx-user'></i> Profile Information
                        </h3>
                        
                        <form method="POST" enctype="multipart/form-data" id="profileForm">
                            <!-- Profile Picture Section -->
                            <div class="profile-upload-area">
                                <div class="profile-image-container">
                                    <img src="<?php echo htmlspecialchars($profileImage); ?>" 
                                         alt="Profile Picture" 
                                         id="profilePreview" 
                                         class="profile-image"
                                         onerror="this.onerror=null;this.src='<?php echo $defaultProfileImage; ?>';"
                                         loading="lazy">
                                    
                                    <?php if (!empty($currentUser['profile_image'])): ?>
                                        <button type="button" 
                                                onclick="removeProfileImage()" 
                                                class="remove-image-btn"
                                                title="Remove profile image">
                                            <i class='bx bx-x'></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="upload-controls">
                                    <input type="file" name="profile_picture" id="profilePicture" accept="image/*" class="file-input" onchange="previewImage(this)">
                                    <button type="button" onclick="document.getElementById('profilePicture').click();" class="btn btn-outline">
                                        <i class='bx bx-upload'></i> 
                                        <?php echo (!empty($currentUser['profile_image'])) ? 'Change Photo' : 'Upload Photo'; ?>
                                    </button>
                                    <p class="upload-hint">JPG, PNG, GIF, or WEBP (max 5MB)</p>
                                </div>
                            </div>
                            
                            <div class="form-grid">
                                <div class="field-group">
                                    <label for="name" class="field-label">
                                        First Name <span class="required">*</span>
                                    </label>
                                    <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($currentUser['name'] ?? ''); ?>" required class="form-input">
                                    <div class="field-validation" id="nameValidation"></div>
                                </div>
                                
                                <div class="field-group">
                                    <label for="mi" class="field-label">Middle Initial</label>
                                    <input type="text" name="mi" id="mi" value="<?php echo htmlspecialchars($currentUser['mi'] ?? ''); ?>" maxlength="5" class="form-input">
                                    <div class="field-validation" id="miValidation"></div>
                                </div>
                                
                                <div class="field-group">
                                    <label for="surname" class="field-label">
                                        Last Name <span class="required">*</span>
                                    </label>
                                    <input type="text" name="surname" id="surname" value="<?php echo htmlspecialchars($currentUser['surname'] ?? ''); ?>" required class="form-input">
                                    <div class="field-validation" id="surnameValidation"></div>
                                </div>
                                
                                <div class="field-group">
                                    <label for="employee_id" class="field-label">Employee ID</label>
                                    <input type="text" name="employee_id" id="employee_id" value="<?php echo htmlspecialchars($currentUser['employee_id'] ?? ''); ?>" class="form-input">
                                    <div class="field-validation" id="employeeIdValidation"></div>
                                </div>
                                
                                <div class="field-group">
                                    <label for="position" class="field-label">Position</label>
                                    <input type="text" name="position" id="position" value="<?php echo htmlspecialchars($currentUser['position'] ?? ''); ?>" class="form-input">
                                    <div class="field-validation" id="positionValidation"></div>
                                </div>
                                
                                <div class="field-group">
                                    <label for="phone" class="field-label">Phone Number</label>
                                    <div class="phone-input">
                                        <span class="phone-prefix">+63</span>
                                        <input type="tel" name="phone" id="phone" value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>" placeholder="9123456789" class="form-input">
                                    </div>
                                    <div class="field-validation" id="phoneValidation"></div>
                                </div>
                                
                                <div class="field-group">
                                    <label for="date_of_birth" class="field-label">Date of Birth</label>
                                    <input type="date" name="date_of_birth" id="date_of_birth" value="<?php echo htmlspecialchars($currentUser['date_of_birth'] ?? ''); ?>" 
                                           max="<?php echo date('Y-m-d', strtotime('-16 years')); ?>" min="<?php echo date('Y-m-d', strtotime('-120 years')); ?>" class="form-input">
                                    <div class="field-validation" id="dobValidation"></div>
                                </div>
                                
                                <div class="field-group">
                                    <label for="hire_date" class="field-label">Hire Date</label>
                                    <input type="date" value="<?php echo htmlspecialchars($currentUser['hire_date'] ?? ''); ?>" readonly class="form-input form-input-readonly">
                                    <div class="field-validation">Set by administrator</div>
                                </div>
                                
                                <div class="field-group">
                                    <label for="email" class="field-label">Email Address</label>
                                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>" readonly class="form-input form-input-readonly">
                                    <div class="field-validation">Contact administrator to change email</div>
                                </div>
                                
                                <div class="field-group">
                                    <label for="department" class="field-label">Department</label>
                                    <input type="text" value="<?php echo htmlspecialchars($currentUser['department_name'] ?? 'N/A'); ?>" readonly class="form-input form-input-readonly">
                                    <div class="field-validation">Set by administrator</div>
                                </div>
                                
                                <div class="field-group form-grid-full">
                                    <label for="address" class="field-label">Address</label>
                                    <textarea name="address" id="address" rows="3" placeholder="Enter your complete address..." class="form-textarea"><?php echo htmlspecialchars($currentUser['address'] ?? ''); ?></textarea>
                                    <div class="field-validation" id="addressValidation"></div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class='bx bx-save'></i> Save Changes
                                </button>
                                <button type="reset" class="btn btn-secondary">
                                    <i class='bx bx-reset'></i> Reset Form
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Security Settings Tab -->
                <div id="security" class="tab-content">
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class='bx bx-shield'></i> Change Password
                        </h3>
                        
                        <form method="POST" id="passwordForm">
                            <div class="password-form-grid">
                                <div class="field-group">
                                    <label for="current_password" class="field-label">
                                        Current Password <span class="required">*</span>
                                    </label>
                                    <input type="password" name="current_password" id="current_password" required class="form-input">
                                    <div class="field-validation" id="currentPasswordValidation"></div>
                                </div>
                                
                                <div class="field-group">
                                    <label for="new_password" class="field-label">
                                        New Password <span class="required">*</span>
                                    </label>
                                    <input type="password" name="new_password" id="new_password" required minlength="8" class="form-input">
                                    <div class="password-strength">
                                        <div class="password-strength-bar" id="passwordStrengthBar"></div>
                                    </div>
                                    <div class="field-validation" id="newPasswordValidation">Password must be at least 8 characters long</div>
                                </div>
                                
                                <div class="field-group">
                                    <label for="confirm_password" class="field-label">
                                        Confirm New Password <span class="required">*</span>
                                    </label>
                                    <input type="password" name="confirm_password" id="confirm_password" required minlength="8" class="form-input">
                                    <div class="field-validation" id="confirmPasswordValidation"></div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="change_password" class="btn btn-primary" id="changePasswordBtn" disabled>
                                    <i class='bx bx-lock'></i> Update Password
                                </button>
                            </div>
                        </form>

                        <!-- Security Information -->
                        <div class="security-info">
                            <h4 class="security-info-title">
                                <i class='bx bx-info-circle'></i> Security Tips
                            </h4>
                            <ul class="security-tips">
                                <li>Use a strong password with at least 8 characters</li>
                                <li>Include uppercase, lowercase, numbers, and special characters</li>
                                <li>Don't reuse passwords from other accounts</li>
                                <li>Change your password regularly</li>
                                <li>Never share your password with others</li>
                            </ul>
                        </div>
                    </div>          
                </div>
            </div>
        </main>
    </section>

    <!-- Remove Profile Image Form (Hidden) -->
    <form method="POST" id="removeImageForm" class="hidden-form">
        <input type="hidden" name="remove_profile_image" value="1">
    </form>
    
    <script src="assets/js/components/navbar.js"></script>
    <script src="assets/js/pages/settings/settings.js"></script>
</body>
</html>