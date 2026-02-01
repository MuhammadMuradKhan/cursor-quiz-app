<?php
/**
 * Authentication Middleware
 */

require_once __DIR__ . '/functions.php';

/**
 * Require user to be logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        jsonResponse(false, null, 'Authentication required');
    }
}

/**
 * Require specific role(s)
 */
function requireRole($roles) {
    requireLogin();
    
    if (!hasRole($roles)) {
        jsonResponse(false, null, 'Access denied. Insufficient permissions.');
    }
}

/**
 * Require admin role
 */
function requireAdmin() {
    requireRole('admin');
}

/**
 * Require faculty role
 */
function requireFaculty() {
    requireRole(['admin', 'faculty']);
}

/**
 * Require student role
 */
function requireStudent() {
    requireRole('student');
}

/**
 * Require admin or faculty
 */
function requireAdminOrFaculty() {
    requireRole(['admin', 'faculty']);
}

/**
 * Validate API key for external requests
 */
function requireApiKey() {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
    
    if (!$apiKey) {
        jsonResponse(false, null, 'API key required');
    }
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id FROM api_keys WHERE key_value = ? AND is_active = TRUE");
    $stmt->execute([$apiKey]);
    
    if (!$stmt->fetch()) {
        jsonResponse(false, null, 'Invalid API key');
    }
}

/**
 * Optional login check (doesn't fail if not logged in)
 */
function optionalLogin() {
    return getCurrentUser();
}

/**
 * Check if current user owns a resource
 */
function isOwner($resourceUserId) {
    $user = getCurrentUser();
    if (!$user) return false;
    
    // Admin can access anything
    if ($user['role'] === 'admin') return true;
    
    return $user['id'] == $resourceUserId;
}

/**
 * Check if faculty owns a course
 */
function isCourseOwner($courseId) {
    $user = getCurrentUser();
    if (!$user) return false;
    
    // Admin can access anything
    if ($user['role'] === 'admin') return true;
    
    if ($user['role'] !== 'faculty') return false;
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND faculty_id = ? AND deleted_at IS NULL");
    $stmt->execute([$courseId, $user['id']]);
    
    return $stmt->fetch() !== false;
}

/**
 * Check if student is enrolled in a course
 */
function isEnrolledInCourse($courseId) {
    $user = getCurrentUser();
    if (!$user) return false;
    
    // Admin and faculty can access any course
    if (in_array($user['role'], ['admin', 'faculty'])) return true;
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT id FROM enrollments 
        WHERE course_id = ? AND student_id = ? AND status = 'approved'
    ");
    $stmt->execute([$courseId, $user['id']]);
    
    return $stmt->fetch() !== false;
}

/**
 * Get request body as JSON
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

/**
 * Validate required fields in input
 */
function validateRequired($input, $fields) {
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($input[$field]) || (is_string($input[$field]) && trim($input[$field]) === '')) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        jsonResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
    }
    
    return true;
}

/**
 * Sanitize input string
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
