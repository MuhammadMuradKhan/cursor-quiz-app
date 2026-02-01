<?php
/**
 * Feedback API Endpoint
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modules/auth/functions.php';
require_once __DIR__ . '/../modules/auth/middleware.php';
require_once __DIR__ . '/../modules/feedback/functions.php';
require_once __DIR__ . '/../modules/email/functions.php';

// Set headers
setCORSHeaders();
header('Content-Type: application/json');

// Get action from request
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Get input data
$input = getJsonInput();
if (empty($input) && $method === 'POST') {
    $input = $_POST;
}

switch ($action) {
    case 'submit':
        handleSubmitFeedback($input);
        break;
        
    case 'list':
        handleListFeedback($input);
        break;
        
    case 'my-feedback':
        handleMyFeedback();
        break;
        
    case 'faculty-feedback':
        handleFacultyFeedback();
        break;
        
    case 'delete':
        handleDeleteFeedback($input);
        break;
        
    case 'stats':
        handleFeedbackStats();
        break;
        
    default:
        jsonResponse(false, null, 'Invalid action. Available actions: submit, list, my-feedback, faculty-feedback, delete, stats');
}

/**
 * Submit feedback
 */
function handleSubmitFeedback($input) {
    requireLogin();
    validateRequired($input, ['feedback_type', 'content']);
    
    $user = getCurrentUser();
    $feedbackType = sanitize($input['feedback_type']);
    $content = sanitize($input['content']);
    $targetId = isset($input['target_id']) ? (int) $input['target_id'] : null;
    
    $result = submitFeedback($user['id'], $feedbackType, $content, $targetId);
    
    // Notify admin/faculty
    if ($result['success']) {
        notifyFeedbackRecipient($feedbackType, $targetId, $user['name']);
    }
    
    jsonResponse($result['success'], $result['data'] ?? null, $result['message']);
}

/**
 * Notify appropriate recipient about feedback
 */
function notifyFeedbackRecipient($feedbackType, $targetId, $submitterName) {
    $pdo = getDBConnection();
    
    // Get admin email for system/general feedback
    if (in_array($feedbackType, ['system', 'general'])) {
        $stmt = $pdo->prepare("SELECT email, name FROM users WHERE role = 'admin' LIMIT 1");
        $stmt->execute();
        $admin = $stmt->fetch();
        if ($admin) {
            sendFeedbackNotificationEmail($admin['email'], $admin['name'], $feedbackType, $submitterName);
        }
    }
    
    // Get faculty email for course/faculty feedback
    if ($feedbackType === 'course' && $targetId) {
        $stmt = $pdo->prepare("
            SELECT u.email, u.name 
            FROM users u
            JOIN courses c ON c.faculty_id = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$targetId]);
        $faculty = $stmt->fetch();
        if ($faculty) {
            sendFeedbackNotificationEmail($faculty['email'], $faculty['name'], $feedbackType, $submitterName);
        }
    }
    
    if ($feedbackType === 'faculty' && $targetId) {
        $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $faculty = $stmt->fetch();
        if ($faculty) {
            sendFeedbackNotificationEmail($faculty['email'], $faculty['name'], $feedbackType, $submitterName);
        }
    }
}

/**
 * List all feedback (admin only)
 */
function handleListFeedback($input) {
    requireAdmin();
    
    $type = isset($input['type']) ? sanitize($input['type']) : null;
    $limit = isset($input['limit']) ? (int) $input['limit'] : 100;
    
    $feedback = getAllFeedback($type, $limit);
    jsonResponse(true, $feedback, 'Feedback retrieved');
}

/**
 * Get current user's submitted feedback
 */
function handleMyFeedback() {
    requireLogin();
    
    $user = getCurrentUser();
    $feedback = getFeedbackByUser($user['id']);
    
    jsonResponse(true, $feedback, 'Feedback retrieved');
}

/**
 * Get feedback for faculty's courses
 */
function handleFacultyFeedback() {
    requireFaculty();
    
    $user = getCurrentUser();
    $feedback = getFeedbackForFaculty($user['id']);
    
    jsonResponse(true, $feedback, 'Feedback retrieved');
}

/**
 * Delete feedback (admin only)
 */
function handleDeleteFeedback($input) {
    requireAdmin();
    validateRequired($input, ['feedback_id']);
    
    $feedbackId = (int) $input['feedback_id'];
    $result = deleteFeedback($feedbackId);
    
    jsonResponse($result['success'], null, $result['message']);
}

/**
 * Get feedback statistics (admin only)
 */
function handleFeedbackStats() {
    requireAdmin();
    
    $stats = getFeedbackStats();
    jsonResponse(true, $stats, 'Stats retrieved');
}
