<?php
    require_once 'includes/config.php';
    require_once 'includes/auth_check.php';

    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit();
    }

    try {
        if (extendSession()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Session extended successfully',
                'remaining_time' => getRemainingSessionTime()
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Session extension failed - not logged in'
            ]);
        }
    } catch (Exception $e) {
        error_log("Session extension error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'An error occurred while extending session'
        ]);
    }
?>