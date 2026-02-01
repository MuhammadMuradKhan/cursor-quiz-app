<?php
/**
 * Courses API Endpoint
 * 
 * Handles: CRUD operations for courses
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modules/auth/functions.php';
require_once __DIR__ . '/../modules/auth/middleware.php';
require_once __DIR__ . '/../modules/courses/functions.php';

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
    case 'list':
        handleListCourses($input);
        break;
        
    case 'get':
        handleGetCourse($input);
        break;
        
    case 'public':
        handleGetPublicCourse($input);
        break;
        
    case 'create':
        handleCreateCourse($input);
        break;
        
    case 'update':
        handleUpdateCourse($input);
        break;
        
    case 'delete':
        handleDeleteCourse($input);
        break;
        
    case 'restore':
        handleRestoreCourse($input);
        break;
        
    case 'regenerate-invite':
        handleRegenerateInvite($input);
        break;
        
    case 'stats':
        handleGetCourseStats($input);
        break;
        
    case 'upload-image':
        handleUploadImage();
        break;
        
    case 'by-invite':
        handleGetByInvite($input);
        break;
        
    default:
        jsonResponse(false, null, 'Invalid action. Available actions: list, get, public, create, update, delete, restore, regenerate-invite, stats, upload-image, by-invite');
}

/**
 * List courses
 */
function handleListCourses($input) {
    $user = getCurrentUser();
    
    if (!$user) {
        jsonResponse(false, null, 'Authentication required');
    }
    
    switch ($user['role']) {
        case 'admin':
            $courses = getAllCourses();
            break;
        case 'faculty':
            $courses = getCoursesByFaculty($user['id']);
            break;
        case 'student':
            $courses = getCoursesForStudent($user['id']);
            break;
        default:
            jsonResponse(false, null, 'Invalid role');
    }
    
    jsonResponse(true, $courses, 'Courses retrieved successfully');
}

/**
 * Get single course
 */
function handleGetCourse($input) {
    requireLogin();
    
    validateRequired($input, ['id']);
    
    $courseId = (int) $input['id'];
    $user = getCurrentUser();
    
    // Check access
    if ($user['role'] === 'student') {
        if (!isEnrolledInCourse($courseId)) {
            jsonResponse(false, null, 'Access denied. Not enrolled in this course.');
        }
    } elseif ($user['role'] === 'faculty') {
        if (!isCourseOwner($courseId)) {
            jsonResponse(false, null, 'Access denied. Not the owner of this course.');
        }
    }
    
    $course = getCourseById($courseId);
    
    if ($course) {
        // Get additional stats for faculty/admin
        if (in_array($user['role'], ['admin', 'faculty'])) {
            $course['stats'] = getCourseStats($courseId);
        }
        jsonResponse(true, $course, 'Course retrieved successfully');
    } else {
        jsonResponse(false, null, 'Course not found');
    }
}

/**
 * Get public course info (no auth required)
 */
function handleGetPublicCourse($input) {
    validateRequired($input, ['id']);
    
    $courseId = (int) $input['id'];
    $course = getPublicCourseInfo($courseId);
    
    if ($course) {
        jsonResponse(true, $course, 'Course retrieved successfully');
    } else {
        jsonResponse(false, null, 'Course not found');
    }
}

/**
 * Get course by invite code
 */
function handleGetByInvite($input) {
    validateRequired($input, ['invite_code']);
    
    $inviteCode = sanitize($input['invite_code']);
    $course = getCourseByInviteCode($inviteCode);
    
    if ($course) {
        // Return limited public info
        jsonResponse(true, [
            'id' => $course['id'],
            'name' => $course['name'],
            'code' => $course['code'],
            'description' => $course['description'],
            'faculty_name' => $course['faculty_name'],
            'thumbnail' => $course['thumbnail']
        ], 'Course found');
    } else {
        jsonResponse(false, null, 'Invalid invite code');
    }
}

/**
 * Create new course
 */
function handleCreateCourse($input) {
    requireFaculty();
    
    validateRequired($input, ['name', 'code']);
    
    $user = getCurrentUser();
    
    $name = sanitize($input['name']);
    $code = sanitize($input['code']);
    $description = isset($input['description']) ? sanitize($input['description']) : '';
    $startDate = isset($input['start_date']) ? sanitize($input['start_date']) : null;
    $endDate = isset($input['end_date']) ? sanitize($input['end_date']) : null;
    
    $result = createCourse($user['id'], $name, $code, $description, $startDate, $endDate);
    
    jsonResponse($result['success'], $result['data'] ?? null, $result['message']);
}

/**
 * Update course
 */
function handleUpdateCourse($input) {
    requireFaculty();
    
    validateRequired($input, ['id']);
    
    $courseId = (int) $input['id'];
    
    // Check ownership
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied. Not the owner of this course.');
    }
    
    $updates = [];
    if (isset($input['name'])) $updates['name'] = sanitize($input['name']);
    if (isset($input['code'])) $updates['code'] = sanitize($input['code']);
    if (isset($input['description'])) $updates['description'] = sanitize($input['description']);
    if (isset($input['start_date'])) $updates['start_date'] = sanitize($input['start_date']);
    if (isset($input['end_date'])) $updates['end_date'] = sanitize($input['end_date']);
    
    $result = updateCourse($courseId, $updates);
    
    jsonResponse($result['success'], null, $result['message']);
}

/**
 * Delete course
 */
function handleDeleteCourse($input) {
    requireFaculty();
    
    validateRequired($input, ['id']);
    
    $courseId = (int) $input['id'];
    
    // Check ownership
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied. Not the owner of this course.');
    }
    
    $result = deleteCourse($courseId);
    
    jsonResponse($result['success'], null, $result['message']);
}

/**
 * Restore deleted course
 */
function handleRestoreCourse($input) {
    requireAdmin();
    
    validateRequired($input, ['id']);
    
    $courseId = (int) $input['id'];
    $result = restoreCourse($courseId);
    
    jsonResponse($result['success'], null, $result['message']);
}

/**
 * Regenerate invite code
 */
function handleRegenerateInvite($input) {
    requireFaculty();
    
    validateRequired($input, ['id']);
    
    $courseId = (int) $input['id'];
    
    // Check ownership
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied. Not the owner of this course.');
    }
    
    $result = regenerateInviteCode($courseId);
    
    jsonResponse($result['success'], $result['data'] ?? null, $result['message']);
}

/**
 * Get course statistics
 */
function handleGetCourseStats($input) {
    requireFaculty();
    
    validateRequired($input, ['id']);
    
    $courseId = (int) $input['id'];
    
    // Check ownership
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied. Not the owner of this course.');
    }
    
    $stats = getCourseStats($courseId);
    
    jsonResponse(true, $stats, 'Stats retrieved successfully');
}

/**
 * Upload course image
 */
function handleUploadImage() {
    requireFaculty();
    
    if (!isset($_FILES['image']) || !isset($_POST['course_id']) || !isset($_POST['type'])) {
        jsonResponse(false, null, 'Missing required fields: image, course_id, type');
    }
    
    $courseId = (int) $_POST['course_id'];
    $type = sanitize($_POST['type']);
    
    // Validate type
    if (!in_array($type, ['thumbnail', 'header'])) {
        jsonResponse(false, null, 'Invalid type. Use: thumbnail or header');
    }
    
    // Check ownership
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied. Not the owner of this course.');
    }
    
    $result = uploadCourseImage($courseId, $_FILES['image'], $type);
    
    jsonResponse($result['success'], $result['data'] ?? null, $result['message']);
}
