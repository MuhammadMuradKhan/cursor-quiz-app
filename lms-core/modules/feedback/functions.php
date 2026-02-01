<?php
/**
 * Feedback Management Functions
 */

require_once __DIR__ . '/../../config.php';

/**
 * Submit feedback
 */
function submitFeedback($userId, $feedbackType, $content, $targetId = null) {
    $pdo = getDBConnection();
    
    // Validate feedback type
    $validTypes = ['course', 'concept', 'quiz', 'faculty', 'system', 'general'];
    if (!in_array($feedbackType, $validTypes)) {
        return ['success' => false, 'message' => 'Invalid feedback type'];
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO feedback (user_id, feedback_type, target_id, content, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$userId, $feedbackType, $targetId, $content]);
    
    return [
        'success' => true,
        'message' => 'Feedback submitted successfully',
        'data' => ['feedback_id' => $pdo->lastInsertId()]
    ];
}

/**
 * Get feedback by ID
 */
function getFeedbackById($feedbackId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT f.*, u.name as user_name, u.email as user_email, u.role as user_role
        FROM feedback f
        JOIN users u ON f.user_id = u.id
        WHERE f.id = ?
    ");
    $stmt->execute([$feedbackId]);
    
    return $stmt->fetch();
}

/**
 * Get all feedback (for admin)
 */
function getAllFeedback($type = null, $limit = 100) {
    $pdo = getDBConnection();
    
    $sql = "
        SELECT f.*, u.name as user_name, u.email as user_email, u.role as user_role
        FROM feedback f
        JOIN users u ON f.user_id = u.id
    ";
    $params = [];
    
    if ($type) {
        $sql .= " WHERE f.feedback_type = ?";
        $params[] = $type;
    }
    
    $sql .= " ORDER BY f.created_at DESC LIMIT ?";
    $params[] = $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Get feedback for a specific target (course, concept, etc.)
 */
function getFeedbackByTarget($feedbackType, $targetId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT f.*, u.name as user_name, u.email as user_email, u.role as user_role
        FROM feedback f
        JOIN users u ON f.user_id = u.id
        WHERE f.feedback_type = ? AND f.target_id = ?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$feedbackType, $targetId]);
    
    return $stmt->fetchAll();
}

/**
 * Get feedback submitted by a user
 */
function getFeedbackByUser($userId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT f.*
        FROM feedback f
        WHERE f.user_id = ?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$userId]);
    
    return $stmt->fetchAll();
}

/**
 * Get feedback for faculty's courses
 */
function getFeedbackForFaculty($facultyId) {
    $pdo = getDBConnection();
    
    // Get course feedback
    $stmt = $pdo->prepare("
        SELECT f.*, u.name as user_name, u.email as user_email, u.role as user_role,
               c.name as course_name
        FROM feedback f
        JOIN users u ON f.user_id = u.id
        JOIN courses c ON f.target_id = c.id
        WHERE f.feedback_type = 'course' AND c.faculty_id = ?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$facultyId]);
    $courseFeedback = $stmt->fetchAll();
    
    // Get faculty feedback
    $stmt = $pdo->prepare("
        SELECT f.*, u.name as user_name, u.email as user_email, u.role as user_role
        FROM feedback f
        JOIN users u ON f.user_id = u.id
        WHERE f.feedback_type = 'faculty' AND f.target_id = ?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$facultyId]);
    $facultyFeedback = $stmt->fetchAll();
    
    return [
        'course_feedback' => $courseFeedback,
        'faculty_feedback' => $facultyFeedback
    ];
}

/**
 * Delete feedback
 */
function deleteFeedback($feedbackId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("DELETE FROM feedback WHERE id = ?");
    $stmt->execute([$feedbackId]);
    
    if ($stmt->rowCount() > 0) {
        return ['success' => true, 'message' => 'Feedback deleted successfully'];
    }
    
    return ['success' => false, 'message' => 'Feedback not found'];
}

/**
 * Get feedback statistics
 */
function getFeedbackStats() {
    $pdo = getDBConnection();
    
    $stmt = $pdo->query("
        SELECT 
            feedback_type,
            COUNT(*) as count
        FROM feedback
        GROUP BY feedback_type
    ");
    
    return $stmt->fetchAll();
}
