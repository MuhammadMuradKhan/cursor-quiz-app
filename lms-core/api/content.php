<?php
/**
 * Content API Endpoint
 * 
 * Handles: Weeks, Lectures, Concepts, Materials CRUD
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modules/auth/functions.php';
require_once __DIR__ . '/../modules/auth/middleware.php';
require_once __DIR__ . '/../modules/content/functions.php';
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
    // Full hierarchy
    case 'hierarchy':
        handleGetHierarchy($input);
        break;
    
    // Weeks
    case 'weeks':
        handleGetWeeks($input);
        break;
    case 'update-week':
        handleUpdateWeek($input);
        break;
    
    // Lectures
    case 'lectures':
        handleGetLectures($input);
        break;
    case 'create-lecture':
        handleCreateLecture($input);
        break;
    case 'update-lecture':
        handleUpdateLecture($input);
        break;
    case 'delete-lecture':
        handleDeleteLecture($input);
        break;
    
    // Concepts
    case 'concepts':
        handleGetConcepts($input);
        break;
    case 'get-concept':
        handleGetConcept($input);
        break;
    case 'create-concept':
        handleCreateConcept($input);
        break;
    case 'update-concept':
        handleUpdateConcept($input);
        break;
    case 'delete-concept':
        handleDeleteConcept($input);
        break;
    
    // Materials
    case 'materials':
        handleGetMaterials($input);
        break;
    case 'create-material':
        handleCreateMaterial($input);
        break;
    case 'upload-material':
        handleUploadMaterial();
        break;
    case 'update-material':
        handleUpdateMaterial($input);
        break;
    case 'delete-material':
        handleDeleteMaterial($input);
        break;
    
    default:
        jsonResponse(false, null, 'Invalid action');
}

// =====================================================
// HIERARCHY
// =====================================================

function handleGetHierarchy($input) {
    requireLogin();
    validateRequired($input, ['course_id']);
    
    $courseId = (int) $input['course_id'];
    $user = getCurrentUser();
    
    // Check access
    if ($user['role'] === 'student') {
        $hierarchy = getCourseContentForStudent($courseId, $user['id']);
        if ($hierarchy === null) {
            jsonResponse(false, null, 'Access denied. Not enrolled in this course.');
        }
    } elseif ($user['role'] === 'faculty') {
        if (!isCourseOwner($courseId)) {
            jsonResponse(false, null, 'Access denied');
        }
        $hierarchy = getCourseContentHierarchy($courseId);
    } else {
        $hierarchy = getCourseContentHierarchy($courseId);
    }
    
    jsonResponse(true, $hierarchy, 'Content hierarchy retrieved');
}

// =====================================================
// WEEKS
// =====================================================

function handleGetWeeks($input) {
    requireLogin();
    validateRequired($input, ['course_id']);
    
    $courseId = (int) $input['course_id'];
    
    // Check access based on role
    $user = getCurrentUser();
    if ($user['role'] === 'student' && !isEnrolledInCourse($courseId)) {
        jsonResponse(false, null, 'Access denied');
    } elseif ($user['role'] === 'faculty' && !isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $weeks = getWeeksByCourse($courseId);
    jsonResponse(true, $weeks, 'Weeks retrieved');
}

function handleUpdateWeek($input) {
    requireFaculty();
    validateRequired($input, ['week_id', 'title']);
    
    $weekId = (int) $input['week_id'];
    $title = sanitize($input['title']);
    
    // Check ownership
    $courseId = getWeekCourseId($weekId);
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $result = updateWeek($weekId, $title);
    jsonResponse($result['success'], null, $result['message']);
}

// =====================================================
// LECTURES
// =====================================================

function handleGetLectures($input) {
    requireLogin();
    validateRequired($input, ['week_id']);
    
    $weekId = (int) $input['week_id'];
    
    // Check access
    $courseId = getWeekCourseId($weekId);
    $user = getCurrentUser();
    if ($user['role'] === 'student' && !isEnrolledInCourse($courseId)) {
        jsonResponse(false, null, 'Access denied');
    } elseif ($user['role'] === 'faculty' && !isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $lectures = getLecturesByWeek($weekId);
    jsonResponse(true, $lectures, 'Lectures retrieved');
}

function handleCreateLecture($input) {
    requireFaculty();
    validateRequired($input, ['week_id', 'title']);
    
    $weekId = (int) $input['week_id'];
    $title = sanitize($input['title']);
    $orderIndex = isset($input['order_index']) ? (int) $input['order_index'] : 0;
    
    // Check ownership
    $courseId = getWeekCourseId($weekId);
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $result = createLecture($weekId, $title, $orderIndex);
    jsonResponse($result['success'], $result['data'] ?? null, $result['message']);
}

function handleUpdateLecture($input) {
    requireFaculty();
    validateRequired($input, ['lecture_id']);
    
    $lectureId = (int) $input['lecture_id'];
    
    // Check ownership
    $courseId = getLectureCourseId($lectureId);
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $updates = [];
    if (isset($input['title'])) $updates['title'] = sanitize($input['title']);
    if (isset($input['order_index'])) $updates['order_index'] = (int) $input['order_index'];
    
    $result = updateLecture($lectureId, $updates);
    jsonResponse($result['success'], null, $result['message']);
}

function handleDeleteLecture($input) {
    requireFaculty();
    validateRequired($input, ['lecture_id']);
    
    $lectureId = (int) $input['lecture_id'];
    
    // Check ownership
    $courseId = getLectureCourseId($lectureId);
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $result = deleteLecture($lectureId);
    jsonResponse($result['success'], null, $result['message']);
}

// =====================================================
// CONCEPTS
// =====================================================

function handleGetConcepts($input) {
    requireLogin();
    validateRequired($input, ['lecture_id']);
    
    $lectureId = (int) $input['lecture_id'];
    
    // Check access
    $courseId = getLectureCourseId($lectureId);
    $user = getCurrentUser();
    if ($user['role'] === 'student' && !isEnrolledInCourse($courseId)) {
        jsonResponse(false, null, 'Access denied');
    } elseif ($user['role'] === 'faculty' && !isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $concepts = getConceptsByLecture($lectureId);
    jsonResponse(true, $concepts, 'Concepts retrieved');
}

function handleGetConcept($input) {
    requireLogin();
    validateRequired($input, ['concept_id']);
    
    $conceptId = (int) $input['concept_id'];
    
    // Check access
    $courseId = getConceptCourseId($conceptId);
    $user = getCurrentUser();
    if ($user['role'] === 'student' && !isEnrolledInCourse($courseId)) {
        jsonResponse(false, null, 'Access denied');
    } elseif ($user['role'] === 'faculty' && !isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $concept = getConceptById($conceptId);
    if ($concept) {
        $concept['materials'] = getMaterialsByConcept($conceptId);
        jsonResponse(true, $concept, 'Concept retrieved');
    }
    jsonResponse(false, null, 'Concept not found');
}

function handleCreateConcept($input) {
    requireFaculty();
    validateRequired($input, ['lecture_id', 'title']);
    
    $lectureId = (int) $input['lecture_id'];
    $title = sanitize($input['title']);
    $orderIndex = isset($input['order_index']) ? (int) $input['order_index'] : 0;
    
    // Check ownership
    $courseId = getLectureCourseId($lectureId);
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $result = createConcept($lectureId, $title, $orderIndex);
    jsonResponse($result['success'], $result['data'] ?? null, $result['message']);
}

function handleUpdateConcept($input) {
    requireFaculty();
    validateRequired($input, ['concept_id']);
    
    $conceptId = (int) $input['concept_id'];
    
    // Check ownership
    $courseId = getConceptCourseId($conceptId);
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $updates = [];
    if (isset($input['title'])) $updates['title'] = sanitize($input['title']);
    if (isset($input['order_index'])) $updates['order_index'] = (int) $input['order_index'];
    
    $result = updateConcept($conceptId, $updates);
    jsonResponse($result['success'], null, $result['message']);
}

function handleDeleteConcept($input) {
    requireFaculty();
    validateRequired($input, ['concept_id']);
    
    $conceptId = (int) $input['concept_id'];
    
    // Check ownership
    $courseId = getConceptCourseId($conceptId);
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $result = deleteConcept($conceptId);
    jsonResponse($result['success'], null, $result['message']);
}

// =====================================================
// MATERIALS
// =====================================================

function handleGetMaterials($input) {
    requireLogin();
    validateRequired($input, ['concept_id']);
    
    $conceptId = (int) $input['concept_id'];
    
    // Check access
    $courseId = getConceptCourseId($conceptId);
    $user = getCurrentUser();
    if ($user['role'] === 'student' && !isEnrolledInCourse($courseId)) {
        jsonResponse(false, null, 'Access denied');
    } elseif ($user['role'] === 'faculty' && !isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $materials = getMaterialsByConcept($conceptId);
    jsonResponse(true, $materials, 'Materials retrieved');
}

function handleCreateMaterial($input) {
    requireFaculty();
    validateRequired($input, ['concept_id', 'type', 'title']);
    
    $conceptId = (int) $input['concept_id'];
    $type = sanitize($input['type']);
    $title = sanitize($input['title']);
    $content = isset($input['content']) ? $input['content'] : null;
    $externalUrl = isset($input['external_url']) ? sanitize($input['external_url']) : null;
    
    // Check ownership
    $courseId = getConceptCourseId($conceptId);
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $result = createMaterial($conceptId, $type, $title, null, $content, $externalUrl);
    jsonResponse($result['success'], $result['data'] ?? null, $result['message']);
}

function handleUploadMaterial() {
    requireFaculty();
    
    if (!isset($_FILES['file']) || !isset($_POST['concept_id']) || !isset($_POST['title'])) {
        jsonResponse(false, null, 'Missing required fields: file, concept_id, title');
    }
    
    $conceptId = (int) $_POST['concept_id'];
    $title = sanitize($_POST['title']);
    $type = isset($_POST['type']) ? sanitize($_POST['type']) : null;
    
    // Check ownership
    $courseId = getConceptCourseId($conceptId);
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $result = uploadMaterial($conceptId, $_FILES['file'], $title, $type);
    jsonResponse($result['success'], $result['data'] ?? null, $result['message']);
}

function handleUpdateMaterial($input) {
    requireFaculty();
    validateRequired($input, ['material_id']);
    
    $materialId = (int) $input['material_id'];
    
    // Check ownership
    $courseId = getMaterialCourseId($materialId);
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $updates = [];
    if (isset($input['title'])) $updates['title'] = sanitize($input['title']);
    if (isset($input['type'])) $updates['type'] = sanitize($input['type']);
    if (isset($input['content'])) $updates['content'] = $input['content'];
    if (isset($input['external_url'])) $updates['external_url'] = sanitize($input['external_url']);
    
    $result = updateMaterial($materialId, $updates);
    jsonResponse($result['success'], null, $result['message']);
}

function handleDeleteMaterial($input) {
    requireFaculty();
    validateRequired($input, ['material_id']);
    
    $materialId = (int) $input['material_id'];
    
    // Check ownership
    $courseId = getMaterialCourseId($materialId);
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $result = deleteMaterial($materialId);
    jsonResponse($result['success'], null, $result['message']);
}
