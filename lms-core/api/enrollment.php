<?php
/**
 * Enrollment API Endpoint
 * 
 * Handles: enrollment requests, approvals, removals
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modules/auth/functions.php';
require_once __DIR__ . '/../modules/auth/middleware.php';
require_once __DIR__ . '/../modules/enrollment/functions.php';
require_once __DIR__ . '/../modules/courses/functions.php';
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
    case 'request':
        handleRequestEnrollment($input);
        break;
        
    case 'register-with-invite':
        handleRegisterWithInvite($input);
        break;
        
    case 'approve':
        handleApproveEnrollment($input);
        break;
        
    case 'reject':
        handleRejectEnrollment($input);
        break;
        
    case 'remove':
        handleRemoveEnrollment($input);
        break;
        
    case 'bulk-approve':
        handleBulkApprove($input);
        break;
        
    case 'bulk-reject':
        handleBulkReject($input);
        break;
        
    case 'list':
        handleListEnrollments($input);
        break;
        
    case 'my-enrollments':
        handleMyEnrollments($input);
        break;
        
    case 'stats':
        handleEnrollmentStats($input);
        break;
        
    default:
        jsonResponse(false, null, 'Invalid action. Available actions: request, register-with-invite, approve, reject, remove, bulk-approve, bulk-reject, list, my-enrollments, stats');
}

/**
 * Request enrollment in a course
 */
function handleRequestEnrollment($input) {
    requireStudent();
    
    validateRequired($input, ['course_id']);
    
    $user = getCurrentUser();
    $courseId = (int) $input['course_id'];
    
    $result = requestEnrollment($user['id'], $courseId);
    
    jsonResponse($result['success'], $result['data'] ?? null, $result['message']);
}

/**
 * Register new student with invite code
 */
function handleRegisterWithInvite($input) {
    validateRequired($input, ['name', 'email', 'password', 'roll_no', 'invite_code']);
    
    $name = sanitize($input['name']);
    $email = sanitize($input['email']);
    $password = $input['password'];
    $rollNo = sanitize($input['roll_no']);
    $inviteCode = sanitize($input['invite_code']);
    
    $result = registerAndEnroll($name, $email, $password, $rollNo, $inviteCode);
    
    if ($result['success']) {
        // Send registration email
        require_once __DIR__ . '/../modules/email/functions.php';
        sendRegistrationEmail($email, $name);
    }
    
    jsonResponse($result['success'], $result['data'] ?? null, $result['message']);
}

/**
 * Approve enrollment
 */
function handleApproveEnrollment($input) {
    requireFaculty();
    
    validateRequired($input, ['enrollment_id']);
    
    $enrollmentId = (int) $input['enrollment_id'];
    
    // Get enrollment details
    $enrollment = getEnrollmentById($enrollmentId);
    if (!$enrollment) {
        jsonResponse(false, null, 'Enrollment not found');
    }
    
    // Check if faculty owns the course
    if (!isCourseOwner($enrollment['course_id'])) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $result = approveEnrollment($enrollmentId);
    
    if ($result['success']) {
        // Send approval email
        sendApprovalEmail(
            $enrollment['student_email'],
            $enrollment['student_name'],
            $enrollment['course_name']
        );
    }
    
    jsonResponse($result['success'], null, $result['message']);
}

/**
 * Reject enrollment
 */
function handleRejectEnrollment($input) {
    requireFaculty();
    
    validateRequired($input, ['enrollment_id']);
    
    $enrollmentId = (int) $input['enrollment_id'];
    
    // Get enrollment details
    $enrollment = getEnrollmentById($enrollmentId);
    if (!$enrollment) {
        jsonResponse(false, null, 'Enrollment not found');
    }
    
    // Check if faculty owns the course
    if (!isCourseOwner($enrollment['course_id'])) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $result = rejectEnrollment($enrollmentId);
    
    jsonResponse($result['success'], null, $result['message']);
}

/**
 * Remove student from course
 */
function handleRemoveEnrollment($input) {
    requireFaculty();
    
    validateRequired($input, ['enrollment_id']);
    
    $enrollmentId = (int) $input['enrollment_id'];
    
    // Get enrollment details
    $enrollment = getEnrollmentById($enrollmentId);
    if (!$enrollment) {
        jsonResponse(false, null, 'Enrollment not found');
    }
    
    // Check if faculty owns the course
    if (!isCourseOwner($enrollment['course_id'])) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $result = removeEnrollment($enrollmentId);
    
    jsonResponse($result['success'], null, $result['message']);
}

/**
 * Bulk approve enrollments
 */
function handleBulkApprove($input) {
    requireFaculty();
    
    validateRequired($input, ['enrollment_ids']);
    
    $enrollmentIds = $input['enrollment_ids'];
    if (!is_array($enrollmentIds)) {
        $enrollmentIds = explode(',', $enrollmentIds);
    }
    $enrollmentIds = array_map('intval', $enrollmentIds);
    
    // Verify all enrollments belong to faculty's courses
    foreach ($enrollmentIds as $id) {
        $enrollment = getEnrollmentById($id);
        if (!$enrollment || !isCourseOwner($enrollment['course_id'])) {
            jsonResponse(false, null, 'Access denied for one or more enrollments');
        }
    }
    
    $result = bulkApproveEnrollments($enrollmentIds);
    
    // Send emails for approved enrollments
    foreach ($enrollmentIds as $id) {
        $enrollment = getEnrollmentById($id);
        if ($enrollment && $enrollment['status'] === 'approved') {
            sendApprovalEmail(
                $enrollment['student_email'],
                $enrollment['student_name'],
                $enrollment['course_name']
            );
        }
    }
    
    jsonResponse($result['success'], null, $result['message']);
}

/**
 * Bulk reject enrollments
 */
function handleBulkReject($input) {
    requireFaculty();
    
    validateRequired($input, ['enrollment_ids']);
    
    $enrollmentIds = $input['enrollment_ids'];
    if (!is_array($enrollmentIds)) {
        $enrollmentIds = explode(',', $enrollmentIds);
    }
    $enrollmentIds = array_map('intval', $enrollmentIds);
    
    // Verify all enrollments belong to faculty's courses
    foreach ($enrollmentIds as $id) {
        $enrollment = getEnrollmentById($id);
        if (!$enrollment || !isCourseOwner($enrollment['course_id'])) {
            jsonResponse(false, null, 'Access denied for one or more enrollments');
        }
    }
    
    $result = bulkRejectEnrollments($enrollmentIds);
    
    jsonResponse($result['success'], null, $result['message']);
}

/**
 * List enrollments for a course
 */
function handleListEnrollments($input) {
    requireFaculty();
    
    validateRequired($input, ['course_id']);
    
    $courseId = (int) $input['course_id'];
    $status = isset($input['status']) ? sanitize($input['status']) : null;
    
    // Check if faculty owns the course
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $enrollments = getEnrollmentsByCourse($courseId, $status);
    
    jsonResponse(true, $enrollments, 'Enrollments retrieved successfully');
}

/**
 * Get current user's enrollments
 */
function handleMyEnrollments($input) {
    requireStudent();
    
    $user = getCurrentUser();
    $status = isset($input['status']) ? sanitize($input['status']) : null;
    
    $enrollments = getEnrollmentsByStudent($user['id'], $status);
    
    jsonResponse(true, $enrollments, 'Enrollments retrieved successfully');
}

/**
 * Get enrollment statistics for a course
 */
function handleEnrollmentStats($input) {
    requireFaculty();
    
    validateRequired($input, ['course_id']);
    
    $courseId = (int) $input['course_id'];
    
    // Check if faculty owns the course
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $stats = getEnrollmentStats($courseId);
    
    jsonResponse(true, $stats, 'Stats retrieved successfully');
}
