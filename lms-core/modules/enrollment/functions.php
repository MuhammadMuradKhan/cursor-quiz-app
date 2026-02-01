<?php
/**
 * Enrollment Management Functions
 */

require_once __DIR__ . '/../../config.php';

/**
 * Request enrollment in a course
 */
function requestEnrollment($studentId, $courseId) {
    $pdo = getDBConnection();
    
    // Check if course exists
    $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$courseId]);
    if (!$stmt->fetch()) {
        return ['success' => false, 'message' => 'Course not found'];
    }
    
    // Check if already enrolled
    $stmt = $pdo->prepare("SELECT id, status FROM enrollments WHERE student_id = ? AND course_id = ?");
    $stmt->execute([$studentId, $courseId]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        if ($existing['status'] === 'approved') {
            return ['success' => false, 'message' => 'Already enrolled in this course'];
        } elseif ($existing['status'] === 'pending') {
            return ['success' => false, 'message' => 'Enrollment request already pending'];
        } elseif ($existing['status'] === 'rejected') {
            // Allow re-request after rejection
            $stmt = $pdo->prepare("UPDATE enrollments SET status = 'pending', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$existing['id']]);
            return ['success' => true, 'message' => 'Enrollment request submitted again'];
        }
    }
    
    // Create enrollment request
    $stmt = $pdo->prepare("INSERT INTO enrollments (course_id, student_id, status, created_at) VALUES (?, ?, 'pending', NOW())");
    $stmt->execute([$courseId, $studentId]);
    
    return [
        'success' => true,
        'message' => 'Enrollment request submitted. Waiting for faculty approval.',
        'data' => ['enrollment_id' => $pdo->lastInsertId()]
    ];
}

/**
 * Approve enrollment
 */
function approveEnrollment($enrollmentId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        UPDATE enrollments 
        SET status = 'approved', updated_at = NOW() 
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->execute([$enrollmentId]);
    
    if ($stmt->rowCount() > 0) {
        return ['success' => true, 'message' => 'Enrollment approved'];
    }
    
    return ['success' => false, 'message' => 'Enrollment not found or already processed'];
}

/**
 * Reject enrollment
 */
function rejectEnrollment($enrollmentId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        UPDATE enrollments 
        SET status = 'rejected', updated_at = NOW() 
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->execute([$enrollmentId]);
    
    if ($stmt->rowCount() > 0) {
        return ['success' => true, 'message' => 'Enrollment rejected'];
    }
    
    return ['success' => false, 'message' => 'Enrollment not found or already processed'];
}

/**
 * Remove student from course
 */
function removeEnrollment($enrollmentId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("DELETE FROM enrollments WHERE id = ?");
    $stmt->execute([$enrollmentId]);
    
    if ($stmt->rowCount() > 0) {
        return ['success' => true, 'message' => 'Student removed from course'];
    }
    
    return ['success' => false, 'message' => 'Enrollment not found'];
}

/**
 * Get enrollment by ID
 */
function getEnrollmentById($enrollmentId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT e.*, u.name as student_name, u.email as student_email, u.roll_no,
               c.name as course_name, c.code as course_code
        FROM enrollments e
        JOIN users u ON e.student_id = u.id
        JOIN courses c ON e.course_id = c.id
        WHERE e.id = ?
    ");
    $stmt->execute([$enrollmentId]);
    
    return $stmt->fetch();
}

/**
 * Get enrollments for a course
 */
function getEnrollmentsByCourse($courseId, $status = null) {
    $pdo = getDBConnection();
    
    $sql = "
        SELECT e.*, u.name as student_name, u.email as student_email, u.roll_no
        FROM enrollments e
        JOIN users u ON e.student_id = u.id
        WHERE e.course_id = ?
    ";
    $params = [$courseId];
    
    if ($status) {
        $sql .= " AND e.status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY e.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Get enrollments for a student
 */
function getEnrollmentsByStudent($studentId, $status = null) {
    $pdo = getDBConnection();
    
    $sql = "
        SELECT e.*, c.name as course_name, c.code as course_code, 
               c.thumbnail, u.name as faculty_name
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        JOIN users u ON c.faculty_id = u.id
        WHERE e.student_id = ? AND c.deleted_at IS NULL
    ";
    $params = [$studentId];
    
    if ($status) {
        $sql .= " AND e.status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY e.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Check if student is enrolled in course
 */
function isStudentEnrolled($studentId, $courseId, $requireApproved = true) {
    $pdo = getDBConnection();
    
    $sql = "SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?";
    $params = [$studentId, $courseId];
    
    if ($requireApproved) {
        $sql .= " AND status = 'approved'";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetch() !== false;
}

/**
 * Bulk approve enrollments
 */
function bulkApproveEnrollments($enrollmentIds) {
    $pdo = getDBConnection();
    
    if (empty($enrollmentIds)) {
        return ['success' => false, 'message' => 'No enrollments specified'];
    }
    
    $placeholders = implode(',', array_fill(0, count($enrollmentIds), '?'));
    
    $stmt = $pdo->prepare("
        UPDATE enrollments 
        SET status = 'approved', updated_at = NOW() 
        WHERE id IN ({$placeholders}) AND status = 'pending'
    ");
    $stmt->execute($enrollmentIds);
    
    $count = $stmt->rowCount();
    
    return ['success' => true, 'message' => "{$count} enrollment(s) approved"];
}

/**
 * Bulk reject enrollments
 */
function bulkRejectEnrollments($enrollmentIds) {
    $pdo = getDBConnection();
    
    if (empty($enrollmentIds)) {
        return ['success' => false, 'message' => 'No enrollments specified'];
    }
    
    $placeholders = implode(',', array_fill(0, count($enrollmentIds), '?'));
    
    $stmt = $pdo->prepare("
        UPDATE enrollments 
        SET status = 'rejected', updated_at = NOW() 
        WHERE id IN ({$placeholders}) AND status = 'pending'
    ");
    $stmt->execute($enrollmentIds);
    
    $count = $stmt->rowCount();
    
    return ['success' => true, 'message' => "{$count} enrollment(s) rejected"];
}

/**
 * Get enrollment statistics for a course
 */
function getEnrollmentStats($courseId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM enrollments
        WHERE course_id = ?
    ");
    $stmt->execute([$courseId]);
    
    return $stmt->fetch();
}

/**
 * Register student via invite link and enroll
 */
function registerAndEnroll($name, $email, $password, $rollNo, $inviteCode) {
    $pdo = getDBConnection();
    
    // Get course by invite code
    $stmt = $pdo->prepare("SELECT id FROM courses WHERE invite_code = ? AND deleted_at IS NULL");
    $stmt->execute([$inviteCode]);
    $course = $stmt->fetch();
    
    if (!$course) {
        return ['success' => false, 'message' => 'Invalid invite code'];
    }
    
    // Register user
    require_once __DIR__ . '/../auth/functions.php';
    $registerResult = registerUser($name, $email, $password, 'student', $rollNo);
    
    if (!$registerResult['success']) {
        return $registerResult;
    }
    
    // Create enrollment
    $studentId = $registerResult['data']['user_id'];
    $enrollResult = requestEnrollment($studentId, $course['id']);
    
    return [
        'success' => true,
        'message' => 'Registration successful. Your enrollment is pending approval.',
        'data' => [
            'user_id' => $studentId,
            'course_id' => $course['id']
        ]
    ];
}
