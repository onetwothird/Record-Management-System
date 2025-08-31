<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Check if user is logged in and is admin
requireAdmin();

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get current user data with additional stats
$user_query = "SELECT u.*, d.department_name, d.department_code, d.head_of_department,
               creator.name as created_by_name, creator.surname as created_by_surname,
               approver.name as approved_by_name, approver.surname as approved_by_surname
               FROM users u 
               LEFT JOIN departments d ON u.department_id = d.id 
               LEFT JOIN users creator ON u.created_by = creator.id
               LEFT JOIN users approver ON u.approved_by = approver.id
               WHERE u.id = ?";
$user_stmt = $pdo->prepare($user_query);
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: login.php");
    exit();
}

// Get departments for dropdown
$dept_query = "SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name";
$dept_stmt = $pdo->prepare($dept_query);
$dept_stmt->execute();
$departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_profile':
            $name = trim($_POST['name'] ?? '');
            $mi = trim($_POST['mi'] ?? '');
            $surname = trim($_POST['surname'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $position = trim($_POST['position'] ?? '');
            $employee_id = trim($_POST['employee_id'] ?? '');
            $department_id = $_POST['department_id'] ?? null;
            $date_of_birth = $_POST['date_of_birth'] ?? null;
            $hire_date = $_POST['hire_date'] ?? null;
            
            // Validation
            if (empty($name) || empty($surname) || empty($email)) {
                $error_message = "Name, surname, and email are required fields.";
            } else {
                // Check if email is already taken by another user
                $email_check = "SELECT id FROM users WHERE email = ? AND id != ?";
                $email_stmt = $pdo->prepare($email_check);
                $email_stmt->execute([$email, $user_id]);
                
                // Check if employee_id is already taken by another user
                $emp_id_error = false;
                if (!empty($employee_id)) {
                    $emp_check = "SELECT id FROM users WHERE employee_id = ? AND id != ?";
                    $emp_stmt = $pdo->prepare($emp_check);
                    $emp_stmt->execute([$employee_id, $user_id]);
                    if ($emp_stmt->fetch()) {
                        $emp_id_error = true;
                    }
                }
                
                if ($email_stmt->fetch()) {
                    $error_message = "Email address is already in use by another user.";
                } elseif ($emp_id_error) {
                    $error_message = "Employee ID is already in use by another user.";
                } else {
                    // Update profile
                    $update_query = "UPDATE users SET 
                                    name = ?, mi = ?, surname = ?, email = ?, phone = ?, 
                                    address = ?, position = ?, employee_id = ?, department_id = ?, 
                                    date_of_birth = ?, hire_date = ?, updated_at = NOW()
                                    WHERE id = ?";
                    $update_stmt = $pdo->prepare($update_query);
                    
                    if ($update_stmt->execute([
                        $name, $mi, $surname, $email, $phone, $address, 
                        $position, $employee_id, $department_id, 
                        $date_of_birth ?: null, $hire_date ?: null, $user_id
                    ])) {
                        // Log activity
                        $log_query = "INSERT INTO activity_logs (user_id, action, resource_type, description, ip_address, user_agent) 
                                     VALUES (?, 'profile_update', 'user', ?, ?, ?)";
                        $log_stmt = $pdo->prepare($log_query);
                        $log_stmt->execute([
                            $user_id, 
                            "Updated profile information",
                            $_SERVER['REMOTE_ADDR'] ?? '',
                            $_SERVER['HTTP_USER_AGENT'] ?? ''
                        ]);
                        
                        $success_message = "Profile updated successfully!";
                        
                        // Refresh user data
                        $user_stmt->execute([$user_id]);
                        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $error_message = "Failed to update profile. Please try again.";
                    }
                }
            }
            break;
            
        case 'change_password':
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error_message = "All password fields are required.";
            } elseif ($new_password !== $confirm_password) {
                $error_message = "New passwords do not match.";
            } elseif (strlen($new_password) < 8) {
                $error_message = "New password must be at least 8 characters long.";
            } elseif (!password_verify($current_password, $user['password'])) {
                $error_message = "Current password is incorrect.";
            } else {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $password_query = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
                $password_stmt = $pdo->prepare($password_query);
                
                if ($password_stmt->execute([$hashed_password, $user_id])) {
                    // Log activity
                    $log_query = "INSERT INTO activity_logs (user_id, action, resource_type, description, ip_address, user_agent) 
                                 VALUES (?, 'password_change', 'user', ?, ?, ?)";
                    $log_stmt = $pdo->prepare($log_query);
                    $log_stmt->execute([
                        $user_id, 
                        "Password changed successfully",
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                    
                    $success_message = "Password changed successfully!";
                } else {
                    $error_message = "Failed to change password. Please try again.";
                }
            }
            break;
            
        case 'upload_image':
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['profile_image'];
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024; // 5MB
                
                if (!in_array($file['type'], $allowed_types)) {
                    $error_message = "Only JPEG, PNG, and GIF images are allowed.";
                } elseif ($file['size'] > $max_size) {
                    $error_message = "Image size must be less than 5MB.";
                } else {
                    // Create upload directory if it doesn't exist
                    $upload_dir = __DIR__ . '/../../uploads/profiles/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    // Generate unique filename
                    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    $db_path = 'uploads/profiles/' . $new_filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        // Delete old profile image if exists
                        if ($user['profile_image'] && file_exists(__DIR__ . '/../../' . $user['profile_image'])) {
                            unlink(__DIR__ . '/../../' . $user['profile_image']);
                        }
                        
                        // Update database
                        $image_query = "UPDATE users SET profile_image = ?, updated_at = NOW() WHERE id = ?";
                        $image_stmt = $pdo->prepare($image_query);
                        
                        if ($image_stmt->execute([$db_path, $user_id])) {
                            // Log activity
                            $log_query = "INSERT INTO activity_logs (user_id, action, resource_type, description, ip_address, user_agent) 
                                         VALUES (?, 'profile_image_update', 'user', ?, ?, ?)";
                            $log_stmt = $pdo->prepare($log_query);
                            $log_stmt->execute([
                                $user_id, 
                                "Profile image updated",
                                $_SERVER['REMOTE_ADDR'] ?? '',
                                $_SERVER['HTTP_USER_AGENT'] ?? ''
                            ]);
                            
                            $success_message = "Profile image updated successfully!";
                            $user['profile_image'] = $db_path;
                        } else {
                            unlink($upload_path);
                            $error_message = "Failed to save image to database.";
                        }
                    } else {
                        $error_message = "Failed to upload image. Please try again.";
                    }
                }
            } else {
                $error_message = "Please select an image to upload.";
            }
            break;
            
        case 'remove_image':
            if ($user['profile_image']) {
                // Delete file
                if (file_exists(__DIR__ . '/../../' . $user['profile_image'])) {
                    unlink(__DIR__ . '/../../' . $user['profile_image']);
                }
                
                // Update database
                $remove_query = "UPDATE users SET profile_image = NULL, updated_at = NOW() WHERE id = ?";
                $remove_stmt = $pdo->prepare($remove_query);
                
                if ($remove_stmt->execute([$user_id])) {
                    $success_message = "Profile image removed successfully!";
                    $user['profile_image'] = null;
                } else {
                    $error_message = "Failed to remove profile image.";
                }
            }
            break;
            
        case 'clear_login_attempts':
            $clear_query = "UPDATE users SET failed_login_attempts = 0, account_locked_until = NULL WHERE id = ?";
            $clear_stmt = $pdo->prepare($clear_query);
            if ($clear_stmt->execute([$user_id])) {
                $success_message = "Login attempts cleared successfully!";
                $user['failed_login_attempts'] = 0;
                $user['account_locked_until'] = null;
            } else {
                $error_message = "Failed to clear login attempts.";
            }
            break;
    }
}

// Get recent activity for this user with more details
$activity_query = "SELECT al.*, u.name, u.surname 
                   FROM activity_logs al
                   LEFT JOIN users u ON al.user_id = u.id
                   WHERE al.user_id = ? 
                   ORDER BY al.created_at DESC 
                   LIMIT 15";
$activity_stmt = $pdo->prepare($activity_query);
$activity_stmt->execute([$user_id]);
$recent_activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get comprehensive account statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM files WHERE uploaded_by = ? AND is_deleted = 0) as total_files,
    (SELECT COUNT(*) FROM activity_logs WHERE user_id = ?) as total_activities,
    (SELECT SUM(file_size) FROM files WHERE uploaded_by = ? AND is_deleted = 0) as total_storage,
    (SELECT COUNT(*) FROM users WHERE created_by = ?) as users_created,
    (SELECT COUNT(*) FROM users WHERE approved_by = ?) as users_approved,
    (SELECT COUNT(DISTINCT DATE(created_at)) FROM activity_logs WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as active_days_month";
$stats_stmt = $pdo->prepare($stats_query);
$stats_stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get login statistics
$login_stats_query = "SELECT 
    COUNT(*) as total_logins,
    MAX(created_at) as last_login_log,
    MIN(created_at) as first_login_log
    FROM activity_logs 
    WHERE user_id = ? AND action = 'login'";
$login_stats_stmt = $pdo->prepare($login_stats_query);
$login_stats_stmt->execute([$user_id]);
$login_stats = $login_stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get department colleagues
$colleagues_query = "SELECT id, name, surname, position, profile_image, last_login
                     FROM users 
                     WHERE department_id = ? AND id != ? AND is_approved = 1
                     ORDER BY name, surname
                     LIMIT 5";
$colleagues_stmt = $pdo->prepare($colleagues_query);
$colleagues_stmt->execute([$user['department_id'], $user_id]);
$colleagues = $colleagues_stmt->fetchAll(PDO::FETCH_ASSOC);

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

function getInitials($name, $surname) {
    return strtoupper(substr($name, 0, 1) . substr($surname, 0, 1));
}

function getAccountAge($created_at) {
    $created = new DateTime($created_at);
    $now = new DateTime();
    $diff = $now->diff($created);
    
    if ($diff->y > 0) {
        return $diff->y . ' year' . ($diff->y > 1 ? 's' : '');
    } elseif ($diff->m > 0) {
        return $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
    } else {
        return $diff->d . ' day' . ($diff->d > 1 ? 's' : '');
    }
}

function getSecurityScore($user) {
    $score = 0;
    if ($user['profile_image']) $score += 10;
    if ($user['phone']) $score += 10;
    if ($user['date_of_birth']) $score += 10;
    if ($user['hire_date']) $score += 10;
    if ($user['employee_id']) $score += 15;
    if ($user['department_id']) $score += 15;
    if ($user['email_verified']) $score += 20;
    if ($user['failed_login_attempts'] == 0) $score += 10;
    return $score;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - <?php echo htmlspecialchars($user['name'] . ' ' . $user['surname']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?= time() ?>">
    <style>
        
        .profile-container {
            padding: 20px;
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - 76px);
            background: #f8f9fa;
            max-width: 1400px;
            margin: 0 auto; /* Center the entire container */
        }

        .profile-container.sidebar-collapsed {
            margin-left: 80px;
        }

        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(50px, -50px);
        }
            .profile-header .row {
            align-items: center;
            text-align: center;
        }

        .profile-avatar {
            position: relative;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: center;
        }
        .avatar-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.3);
            object-fit: cover;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .avatar-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 700;
            border: 4px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

            .profile-badge {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: #28a745;
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            border: 3px solid white;
        }

                .profile-info {
            text-align: center;
        }

        .profile-info h2 {
            margin-bottom: 0.5rem;
            font-weight: 700;
            font-size: 2.2rem;
        }

        .profile-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            opacity: 0.95;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.75rem;
            border-radius: 10px;
            backdrop-filter: blur(10px);
            text-align: left; /* Keep text left-aligned within items */
        }

        .meta-icon {
            width: 20px;
            text-align: center;
            flex-shrink: 0;
        }
                
                .profile-cards {
            display: flex;
            flex-direction: column;
            align-items: center;
            max-width: 1000px;
            margin: 0 auto;
            gap: 2rem;
        }

        .profile-main {
            width: 100%;
            max-width: 900px;
        }

        .profile-sidebar {
            width: 100%;
            max-width: 350px;
            margin-top: 0;
        }

        .profile-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border: none;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            width: 100%;
        }

        .profile-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
        }
                
            .card-header-custom {
            background: linear-gradient(135deg, #f8f9ff 0%, #e3e6f0 100%);
            border-bottom: 1px solid #e3e6f0;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .card-header-custom h5 {
            margin: 0;
            color: #2d3436;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .card-header-custom i {
            background: #667eea;
            color: white;
            padding: 8px;
            border-radius: 10px;
            font-size: 1rem;
        }
                
        .form-section {
            margin-bottom: 2.5rem;
        }
                .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #2d3436;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 3px solid #667eea;
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 50px;
            height: 3px;
            background: #764ba2;
        }

        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .stat-item {
            text-align: center;
            padding: 1.5rem 1rem;
            border-radius: 15px;
            background: linear-gradient(135deg, #f8f9ff 0%, #e3e6f0 100%);
            margin-bottom: 1rem;
            transition: transform 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-2px);
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 800;
            color: #667eea;
            margin-bottom: 0.5rem;
            display: block;
        }

        .stat-label {
            color: #636e72;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .activity-item {
            padding: 1rem 0;
            border-bottom: 1px solid #f1f3f4;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: background-color 0.3s ease;
        }

        .activity-item:hover {
            background-color: #f8f9fa;
            margin: 0 -1rem;
            padding: 1rem;
            border-radius: 10px;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
        }
                
            .activity-icon.profile { background: #e3f2fd; color: #1976d2; }
        .activity-icon.security { background: #fff3e0; color: #f57c00; }
        .activity-icon.file { background: #e8f5e8; color: #4caf50; }
        .activity-icon.user { background: #fce4ec; color: #c2185b; }

        .security-score {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .score-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: conic-gradient(#4caf50 var(--score), #e0e0e0 0);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .score-circle::before {
            content: attr(data-score) '%';
            position: absolute;
            background: white;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            color: #4caf50;
        }

        .colleagues-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .colleague-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: #f8f9ff;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .colleague-item:hover {
            background: #e3e6f0;
            transform: translateX(5px);
        }

        .colleague-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
        }

        .colleague-placeholder {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
            color: white;
        }

        .verification-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .verification-badge.verified {
            background: #d4edda;
            color: #155724;
        }

        .verification-badge.unverified {
            background: #f8d7da;
            color: #721c24;
        }

        .alert-custom {
            border-radius: 15px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }
        @media (min-width: 992px) {
            .profile-cards {
                flex-direction: row;
                justify-content: center;
                align-items: flex-start;
            }
            
            .profile-main {
                flex: 2;
                max-width: 700px;
            }
            
            .profile-sidebar {
                flex: 1;
                max-width: 300px;
                margin-top: 0;
                margin-left: 2rem;
            }
            
            .profile-header .row {
                text-align: left;
            }
            
            .profile-info {
                text-align: left;
            }
            
            .profile-avatar {
                justify-content: flex-start;
                margin-bottom: 1rem;
            }
        }

        @media (max-width: 1200px) {
            .profile-cards {
                flex-direction: column;
                align-items: center;
            }
            
            .profile-main,
            .profile-sidebar {
                max-width: 100%;
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .profile-container {
                margin-left: 0;
                padding: 15px;
            }
            
            .profile-meta {
                grid-template-columns: 1fr;
            }
            
            .profile-header {
                padding: 1.5rem;
                text-align: center;
            }
            
            .profile-info {
                text-align: center;
            }
            
            .profile-avatar {
                justify-content: center;
            }
            
            .profile-info h2 {
                font-size: 1.8rem;
            }
            
            .meta-item {
                justify-content: center;
                text-align: center;
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
    
        <div class="profile-container" id="profile-container">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <div class="profile-avatar">
                            <?php if ($user['profile_image']): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                    alt="Profile" class="avatar-image">
                            <?php else: ?>
                                <div class="avatar-placeholder">
                                    <?php echo getInitials($user['name'], $user['surname']); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($user['role'] === 'admin' || $user['role'] === 'super_admin'): ?>
                                <div class="profile-badge" title="<?php echo ucfirst($user['role']); ?>">
                                    <i class="fas <?php echo $user['role'] === 'super_admin' ? 'fa-crown' : 'fa-shield-alt'; ?>"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col">
                        <div class="profile-info">
                            <h2><?php echo htmlspecialchars($user['name'] . ' ' . ($user['mi'] ? $user['mi'] . ' ' : '') . $user['surname']); ?>
                                <?php if (!$user['email_verified']): ?>
                                    <span class="verification-badge unverified ms-2">
                                        <i class="fas fa-exclamation-triangle"></i> Unverified
                                    </span>
                                <?php else: ?>
                                    <span class="verification-badge verified ms-2">
                                        <i class="fas fa-check-circle"></i> Verified
                                    </span>
                                <?php endif; ?>
                            </h2>
                            <p class="mb-2 fs-5"><?php echo htmlspecialchars($user['position'] ?? 'Administrator'); ?></p>
                            
                            <div class="profile-meta">
                                <div class="meta-item">
                                    <i class="fas fa-building meta-icon"></i>
                                    <div>
                                        <small class="d-block opacity-75">Department</small>
                                        <strong><?php echo htmlspecialchars($user['department_name'] ?? 'No Department'); ?></strong>
                                    </div>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-envelope meta-icon"></i>
                                    <div>
                                        <small class="d-block opacity-75">Email</small>
                                        <strong><?php echo htmlspecialchars($user['email']); ?></strong>
                                    </div>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-id-badge meta-icon"></i>
                                    <div>
                                        <small class="d-block opacity-75">Employee ID</small>
                                        <strong><?php echo htmlspecialchars($user['employee_id'] ?? 'Not Set'); ?></strong>
                                    </div>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-calendar-alt meta-icon"></i>
                                    <div>
                                        <small class="d-block opacity-75">Account Age</small>
                                        <strong><?php echo getAccountAge($user['created_at']); ?></strong>
                                    </div>
                                </div>
                                <?php if ($user['last_login']): ?>
                                <div class="meta-item">
                                    <i class="fas fa-clock meta-icon"></i>
                                    <div>
                                        <small class="d-block opacity-75">Last Login</small>
                                        <strong><?php echo date('M j, Y g:i A', strtotime($user['last_login'])); ?></strong>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="meta-item">
                                    <i class="fas fa-users-cog meta-icon"></i>
                                    <div>
                                        <small class="d-block opacity-75">Access Level</small>
                                        <strong><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show alert-custom">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show alert-custom">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Security Alert -->
            <?php if ($user['failed_login_attempts'] > 0 || $user['account_locked_until']): ?>
                <div class="alert alert-warning alert-dismissible fade show alert-custom">
                    <i class="fas fa-shield-alt me-2"></i>
                    Security Notice: 
                    <?php if ($user['account_locked_until'] && strtotime($user['account_locked_until']) > time()): ?>
                        Your account was temporarily locked due to failed login attempts.
                    <?php else: ?>
                        <?php echo $user['failed_login_attempts']; ?> failed login attempt(s) recorded.
                    <?php endif; ?>
                    <form method="POST" class="d-inline ms-2">
                        <input type="hidden" name="action" value="clear_login_attempts">
                        <button type="submit" class="btn btn-sm btn-outline-warning">Clear Attempts</button>
                    </form>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="profile-cards">
                <!-- Main Profile Content -->
                <div class="profile-main">
                    <!-- Personal Information -->
                    <div class="card profile-card mb-4">
                        <div class="card-header-custom">
                            <i class="fas fa-user"></i>
                            <h5>Personal Information</h5>
                            <div class="ms-auto">
                                <small class="text-muted">
                                    Last updated: <?php echo date('M j, Y g:i A', strtotime($user['updated_at'])); ?>
                                </small>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="form-section" id="profileForm">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="section-title">
                                    <i class="fas fa-user-circle me-2"></i>Basic Information
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">First Name *</label>
                                        <input type="text" class="form-control" name="name" 
                                            value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label fw-bold">M.I.</label>
                                        <input type="text" class="form-control" name="mi" maxlength="5"
                                            value="<?php echo htmlspecialchars($user['mi'] ?? ''); ?>"
                                            placeholder="Optional">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Last Name *</label>
                                        <input type="text" class="form-control" name="surname" 
                                            value="<?php echo htmlspecialchars($user['surname']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Email Address *</label>
                                        <div class="input-group">
                                            <input type="email" class="form-control" name="email" 
                                                value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                            <span class="input-group-text">
                                                <?php if ($user['email_verified']): ?>
                                                    <i class="fas fa-check-circle text-success" title="Verified"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-exclamation-triangle text-warning" title="Unverified"></i>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Phone Number</label>
                                        <input type="tel" class="form-control" name="phone" 
                                            value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                            placeholder="+63 XXX XXX XXXX">
                                    </div>
                                </div>
                                
                                <div class="section-title">
                                    <i class="fas fa-briefcase me-2"></i>Professional Information
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Employee ID</label>
                                        <input type="text" class="form-control" name="employee_id" 
                                            value="<?php echo htmlspecialchars($user['employee_id'] ?? ''); ?>"
                                            placeholder="e.g., ITD001">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Position</label>
                                        <input type="text" class="form-control" name="position" 
                                            value="<?php echo htmlspecialchars($user['position'] ?? ''); ?>"
                                            placeholder="e.g., Department Head">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Department</label>
                                        <select class="form-select" name="department_id">
                                            <option value="">No Department</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?php echo $dept['id']; ?>" 
                                                        <?php echo $user['department_id'] == $dept['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                                    <?php if ($dept['department_code']): ?>
                                                        (<?php echo htmlspecialchars($dept['department_code']); ?>)
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Hire Date</label>
                                        <input type="date" class="form-control" name="hire_date" 
                                            value="<?php echo $user['hire_date'] ?? ''; ?>">
                                    </div>
                                </div>
                                
                                <div class="section-title">
                                    <i class="fas fa-info-circle me-2"></i>Additional Information
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Date of Birth</label>
                                        <input type="date" class="form-control" name="date_of_birth" 
                                            value="<?php echo $user['date_of_birth'] ?? ''; ?>"
                                            max="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Address</label>
                                        <textarea class="form-control" name="address" rows="3" 
                                                placeholder="Complete address"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                        <i class="fas fa-undo me-2"></i>Reset Changes
                                    </button>
                                    <button type="submit" class="btn btn-gradient">
                                        <i class="fas fa-save me-2"></i>Update Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Profile Image Management -->
                    <div class="card profile-card mb-4">
                        <div class="card-header-custom">
                            <i class="fas fa-image"></i>
                            <h5>Profile Image</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <?php if ($user['profile_image']): ?>
                                        <div class="text-center mb-3">
                                            <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                                alt="Current Profile" class="img-thumbnail" 
                                                style="max-width: 200px; border-radius: 15px;">
                                            <div class="mt-2">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="remove_image">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" 
                                                            onclick="return confirm('Are you sure you want to remove your profile image?')">
                                                        <i class="fas fa-trash me-2"></i>Remove Image
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center mb-3">
                                            <div class="avatar-placeholder mx-auto" style="width: 120px; height: 120px;">
                                                <?php echo getInitials($user['name'], $user['surname']); ?>
                                            </div>
                                            <p class="text-muted mt-2">No profile image set</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <form method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="action" value="upload_image">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Upload New Image</label>
                                            <input type="file" class="form-control" name="profile_image" 
                                                accept="image/jpeg,image/jpg,image/png,image/gif" required
                                                onchange="previewImage(this)">
                                            <div class="form-text">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Supported: JPEG, PNG, GIF. Max: 5MB.
                                            </div>
                                        </div>
                                        <div id="imagePreview" class="mb-3" style="display: none;">
                                            <img id="previewImg" class="img-thumbnail" style="max-width: 150px; border-radius: 10px;">
                                        </div>
                                        <button type="submit" class="btn btn-gradient w-100">
                                            <i class="fas fa-upload me-2"></i>Upload Image
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Security Settings -->
                    <div class="card profile-card mb-4">
                        <div class="card-header-custom">
                            <i class="fas fa-shield-alt"></i>
                            <h5>Security Settings</h5>
                            <div class="ms-auto">
                                <?php $security_score = getSecurityScore($user); ?>
                                <span class="badge bg-<?php echo $security_score >= 80 ? 'success' : ($security_score >= 60 ? 'warning' : 'danger'); ?>">
                                    Security Score: <?php echo $security_score; ?>%
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Security Score Display -->
                            <div class="security-score">
                                <div class="score-circle" data-score="<?php echo $security_score; ?>" 
                                    style="--score: <?php echo $security_score * 3.6; ?>deg;"></div>
                                <div>
                                    <h6 class="mb-1">Profile Completeness</h6>
                                    <p class="mb-0 text-muted">
                                        <?php 
                                        if ($security_score >= 80) echo "Excellent! Your profile is well-secured.";
                                        elseif ($security_score >= 60) echo "Good! Consider adding more details.";
                                        else echo "Needs improvement. Please complete your profile.";
                                        ?>
                                    </p>
                                </div>
                            </div>
                            
                            <form method="POST" id="passwordForm">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="section-title">
                                    <i class="fas fa-key me-2"></i>Change Password
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Current Password *</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="current_password" 
                                            id="currentPassword" required placeholder="Enter current password">
                                        <button type="button" class="btn btn-outline-secondary" 
                                                onclick="togglePassword('currentPassword')">
                                            <i class="fas fa-eye" id="currentPasswordIcon"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">New Password *</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="new_password" 
                                            id="newPassword" required onkeyup="checkPasswordStrength()"
                                            placeholder="Enter new password">
                                        <button type="button" class="btn btn-outline-secondary" 
                                                onclick="togglePassword('newPassword')">
                                            <i class="fas fa-eye" id="newPasswordIcon"></i>
                                        </button>
                                    </div>
                                    <div class="password-strength" id="passwordStrength"></div>
                                    <div class="form-text" id="passwordHelp">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Password must be at least 8 characters long
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Confirm New Password *</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="confirm_password" 
                                            id="confirmPassword" required onkeyup="checkPasswordMatch()"
                                            placeholder="Confirm new password">
                                        <button type="button" class="btn btn-outline-secondary" 
                                                onclick="togglePassword('confirmPassword')">
                                            <i class="fas fa-eye" id="confirmPasswordIcon"></i>
                                        </button>
                                    </div>
                                    <div class="form-text" id="passwordMatch"></div>
                                </div>
                                
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-lock me-2"></i>Change Password
                                    </button>
                                </div>
                            </form>
                            
                            <hr class="my-4">
                            
                            <!-- Enhanced Security Information -->
                            <div class="security-info">
                                <h6 class="fw-bold mb-3">
                                    <i class="fas fa-info-circle me-2"></i>Account Information
                                </h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="bg-light p-3 rounded-3">
                                            <small class="text-muted d-block">Account Created</small>
                                            <strong><?php echo date('M j, Y g:i A', strtotime($user['created_at'])); ?></strong>
                                            <?php if ($user['created_by_name']): ?>
                                                <br><small class="text-info">
                                                    Created by: <?php echo htmlspecialchars($user['created_by_name'] . ' ' . $user['created_by_surname']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="bg-light p-3 rounded-3">
                                            <small class="text-muted d-block">Last Updated</small>
                                            <strong><?php echo date('M j, Y g:i A', strtotime($user['updated_at'])); ?></strong>
                                        </div>
                                    </div>
                                    <?php if ($user['approved_at']): ?>
                                    <div class="col-md-6">
                                        <div class="bg-light p-3 rounded-3">
                                            <small class="text-muted d-block">Account Approved</small>
                                            <strong><?php echo date('M j, Y g:i A', strtotime($user['approved_at'])); ?></strong>
                                            <?php if ($user['approved_by_name']): ?>
                                                <br><small class="text-success">
                                                    Approved by: <?php echo htmlspecialchars($user['approved_by_name'] . ' ' . $user['approved_by_surname']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($user['last_login']): ?>
                                    <div class="col-md-6">
                                        <div class="bg-light p-3 rounded-3">
                                            <small class="text-muted d-block">Last Login</small>
                                            <strong><?php echo date('M j, Y g:i A', strtotime($user['last_login'])); ?></strong>
                                            <?php if ($login_stats['total_logins']): ?>
                                                <br><small class="text-primary">
                                                    Total logins: <?php echo number_format($login_stats['total_logins']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                    
            </div>
        </div>
        
        <!-- Profile Completion Modal -->
        <div class="modal fade" id="profileCompletionModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-user-check me-2"></i>Complete Your Profile
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Missing Information:</h6>
                                <ul class="list-unstyled">
                                    <?php if (!$user['profile_image']): ?>
                                        <li class="mb-2"><i class="fas fa-times text-danger me-2"></i>Profile Image</li>
                                    <?php endif; ?>
                                    <?php if (!$user['phone']): ?>
                                        <li class="mb-2"><i class="fas fa-times text-danger me-2"></i>Phone Number</li>
                                    <?php endif; ?>
                                    <?php if (!$user['employee_id']): ?>
                                        <li class="mb-2"><i class="fas fa-times text-danger me-2"></i>Employee ID</li>
                                    <?php endif; ?>
                                    <?php if (!$user['department_id']): ?>
                                        <li class="mb-2"><i class="fas fa-times text-danger me-2"></i>Department</li>
                                    <?php endif; ?>
                                    <?php if (!$user['date_of_birth']): ?>
                                        <li class="mb-2"><i class="fas fa-times text-danger me-2"></i>Date of Birth</li>
                                    <?php endif; ?>
                                    <?php if (!$user['hire_date']): ?>
                                        <li class="mb-2"><i class="fas fa-times text-danger me-2"></i>Hire Date</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <div class="text-center">
                                    <div class="score-circle mb-3" data-score="<?php echo $security_score; ?>" 
                                        style="--score: <?php echo $security_score * 3.6; ?>deg;"></div>
                                    <p class="text-muted">
                                        A complete profile improves security and helps colleagues find you.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Later</button>
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="scrollToProfileForm()">
                            Complete Now
                        </button>
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
            const profileContainer = document.querySelector('#profile-container');
            
            function updateLayout() {
                if (sidebar && sidebar.classList.contains('collapsed')) {
                    profileContainer.classList.add('sidebar-collapsed');
                } else {
                    profileContainer.classList.remove('sidebar-collapsed');
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
            
            // Show profile completion modal if score is low
            <?php if ($security_score < 60): ?>
                setTimeout(() => {
                    const modal = new bootstrap.Modal(document.getElementById('profileCompletionModal'));
                    modal.show();
                }, 2000);
            <?php endif; ?>
        });
        
        // Enhanced form functions
        function resetForm() {
            if (confirm('Are you sure you want to reset all changes?')) {
                document.getElementById('profileForm').reset();
                // Clear any visual indicators of changes
                document.querySelectorAll('.form-control, .form-select').forEach(el => {
                    el.style.backgroundColor = '';
                });
            }
        }
        
        function scrollToProfileForm() {
            document.querySelector('#profileForm').scrollIntoView({ 
                behavior: 'smooth',
                block: 'start'
            });
        }
        
        // Image preview function
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const previewImg = document.getElementById('previewImg');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }
        
        // Password visibility toggle
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + 'Icon');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
        
        // Enhanced password strength checker
        function checkPasswordStrength() {
            const password = document.getElementById('newPassword').value;
            const strengthBar = document.getElementById('passwordStrength');
            const helpText = document.getElementById('passwordHelp');
            
            let score = 0;
            let feedback = [];
            
            if (password.length >= 8) {
                score++;
            } else {
                feedback.push('at least 8 characters');
            }
            
            if (password.match(/[a-z]/)) {
                score++;
            } else {
                feedback.push('lowercase letters');
            }
            
            if (password.match(/[A-Z]/)) {
                score++;
            } else {
                feedback.push('uppercase letters');
            }
            
            if (password.match(/[0-9]/)) {
                score++;
            } else {
                feedback.push('numbers');
            }
            
            if (password.match(/[^a-zA-Z0-9]/)) {
                score++;
            } else {
                feedback.push('special characters');
            }
            
            if (password.length === 0) {
                strengthBar.style.width = '0%';
                strengthBar.className = 'password-strength';
                helpText.innerHTML = '<i class="fas fa-info-circle me-1"></i>Password must be at least 8 characters long';
                helpText.className = 'form-text';
            } else if (score <= 2) {
                strengthBar.style.width = '33%';
                strengthBar.className = 'password-strength strength-weak';
                helpText.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Weak - Add: ' + feedback.join(', ');
                helpText.className = 'form-text text-danger';
            } else if (score <= 3) {
                strengthBar.style.width = '66%';
                strengthBar.className = 'password-strength strength-medium';
                helpText.innerHTML = '<i class="fas fa-info-circle me-1"></i>Medium - Consider adding: ' + feedback.join(', ');
                helpText.className = 'form-text text-warning';
            } else {
                strengthBar.style.width = '100%';
                strengthBar.className = 'password-strength strength-strong';
                helpText.innerHTML = '<i class="fas fa-check-circle me-1"></i>Strong password!';
                helpText.className = 'form-text text-success';
            }
            
            checkPasswordMatch();
        }
        
        // Enhanced password match checker
        function checkPasswordMatch() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const matchText = document.getElementById('passwordMatch');
            
            if (confirmPassword.length === 0) {
                matchText.textContent = '';
                matchText.className = 'form-text';
            } else if (newPassword === confirmPassword) {
                matchText.innerHTML = '<i class="fas fa-check-circle me-1"></i>Passwords match';
                matchText.className = 'form-text text-success';
            } else {
                matchText.innerHTML = '<i class="fas fa-times-circle me-1"></i>Passwords do not match';
                matchText.className = 'form-text text-danger';
            }
        }
        
        // Enhanced form validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (newPassword.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
            
            return confirm('Are you sure you want to change your password? You will need to log in again with your new password.');
        });
        
        // Track form changes
        const originalFormData = new FormData(document.getElementById('profileForm'));
        const formInputs = document.querySelectorAll('#profileForm input, #profileForm select, #profileForm textarea');
        
        formInputs.forEach(input => {
            input.addEventListener('input', function() {
                // Highlight changed fields
                const originalValue = originalFormData.get(this.name) || '';
                if (this.value !== originalValue) {
                    this.style.backgroundColor = '#fff3cd';
                    this.style.borderColor = '#ffc107';
                } else {
                    this.style.backgroundColor = '';
                    this.style.borderColor = '';
                }
                
                // Check if form has unsaved changes
                checkUnsavedChanges();
            });
        });
        
        // Warn about unsaved changes
        let hasUnsavedChanges = false;
        
        function checkUnsavedChanges() {
            const currentFormData = new FormData(document.getElementById('profileForm'));
            hasUnsavedChanges = false;
            
            for (let [key, value] of currentFormData.entries()) {
                if (value !== (originalFormData.get(key) || '')) {
                    hasUnsavedChanges = true;
                    break;
                }
            }
            
            // Show/hide unsaved changes indicator
            const indicator = document.getElementById('unsavedIndicator');
            if (hasUnsavedChanges && !indicator) {
                const alert = document.createElement('div');
                alert.id = 'unsavedIndicator';
                alert.className = 'alert alert-warning alert-dismissible fade show position-fixed';
                alert.style.cssText = 'top: 20px; right: 20px; z-index: 1060; min-width: 300px;';
                alert.innerHTML = `
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    You have unsaved changes
                    <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
                `;
                document.body.appendChild(alert);
            } else if (!hasUnsavedChanges && indicator) {
                indicator.remove();
            }
        }
        
        // Remove unsaved changes warning on successful save
        <?php if ($success_message): ?>
            const indicator = document.getElementById('unsavedIndicator');
            if (indicator) indicator.remove();
            hasUnsavedChanges = false;
        <?php endif; ?>
        
        // Warn before leaving page if there are unsaved changes
        window.addEventListener('beforeunload', function(e) {
            if (hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
        
        // Load more activities function
        function loadMoreActivities() {
            // This would typically make an AJAX call to load more activities
            // For now, we'll just redirect to the full activity log
            window.location.href = 'activity_logs.php?user_id=<?php echo $user_id; ?>';
        }
        
        // Auto-save functionality (optional - can be disabled)
        let autoSaveTimeout;
        formInputs.forEach(input => {
            input.addEventListener('input', function() {
                clearTimeout(autoSaveTimeout);
                autoSaveTimeout = setTimeout(() => {
                    // Auto-save draft to session storage (if enabled)
                    if (document.getElementById('autoSaveToggle')?.checked) {
                        saveFormDraft();
                    }
                }, 2000);
            });
        });
        
        function saveFormDraft() {
            const formData = {};
            formInputs.forEach(input => {
                formData[input.name] = input.value;
            });
            // Save to session (would need server-side implementation)
            console.log('Auto-saving draft...', formData);
        }
        
        // Enhanced tooltips for better UX
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Form validation enhancements
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const employeeId = document.querySelector('input[name="employee_id"]').value;
            const email = document.querySelector('input[name="email"]').value;
            
            // Enhanced email validation
            if (email && !email.includes('@')) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }
            
            // Employee ID format validation (if you have specific format requirements)
            if (employeeId && employeeId.length > 0 && employeeId.length < 3) {
                e.preventDefault();
                alert('Employee ID should be at least 3 characters long.');
                return false;
            }
            
            return true;
        });
        
        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+S to save profile
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                if (hasUnsavedChanges) {
                    document.getElementById('profileForm').submit();
                }
            }
            
            // Ctrl+R to reset form
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                resetForm();
            }
        });
        
        // Initialize password strength indicator styles
        const style = document.createElement('style');
        style.textContent = `
            .password-strength {
                height: 6px;
                border-radius: 3px;
                margin-top: 0.5rem;
                transition: all 0.3s ease;
                background: #e9ecef;
            }
            
            .strength-weak { 
                background: linear-gradient(90deg, #ff5252 var(--width, 33%), #e9ecef 0);
            }
            .strength-medium { 
                background: linear-gradient(90deg, #ff9800 var(--width, 66%), #e9ecef 0);
            }
            .strength-strong { 
                background: linear-gradient(90deg, #4caf50 var(--width, 100%), #e9ecef 0);
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>