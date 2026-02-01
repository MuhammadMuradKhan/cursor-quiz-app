<?php
/**
 * Course Management Functions
 */

require_once __DIR__ . '/../../config.php';

/**
 * Create a new course
 */
function createCourse($facultyId, $name, $code, $description = '', $startDate = null, $endDate = null) {
    $pdo = getDBConnection();
    
    // Generate unique invite code
    $inviteCode = generateInviteCode();
    
    $stmt = $pdo->prepare("
        INSERT INTO courses (faculty_id, name, code, description, start_date, end_date, invite_code, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    try {
        $stmt->execute([$facultyId, $name, $code, $description, $startDate, $endDate, $inviteCode]);
        $courseId = $pdo->lastInsertId();
        
        // Create default 16 weeks
        createDefaultWeeks($courseId);
        
        return [
            'success' => true,
            'message' => 'Course created successfully',
            'data' => [
                'course_id' => $courseId,
                'invite_code' => $inviteCode,
                'invite_link' => APP_URL . '/public/register.html?invite=' . $inviteCode
            ]
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Failed to create course: ' . $e->getMessage()];
    }
}

/**
 * Generate unique invite code
 */
function generateInviteCode() {
    return strtoupper(bin2hex(random_bytes(4))) . '-' . date('Y');
}

/**
 * Create default 16 weeks for a course
 */
function createDefaultWeeks($courseId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("INSERT INTO weeks (course_id, week_number, title, created_at) VALUES (?, ?, ?, NOW())");
    
    for ($i = 1; $i <= 16; $i++) {
        $stmt->execute([$courseId, $i, "Week {$i}"]);
    }
}

/**
 * Get course by ID
 */
function getCourseById($courseId, $includeDeleted = false) {
    $pdo = getDBConnection();
    
    $sql = "
        SELECT c.*, u.name as faculty_name, u.email as faculty_email
        FROM courses c
        JOIN users u ON c.faculty_id = u.id
        WHERE c.id = ?
    ";
    
    if (!$includeDeleted) {
        $sql .= " AND c.deleted_at IS NULL";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$courseId]);
    
    return $stmt->fetch();
}

/**
 * Get course by invite code
 */
function getCourseByInviteCode($inviteCode) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT c.*, u.name as faculty_name, u.email as faculty_email
        FROM courses c
        JOIN users u ON c.faculty_id = u.id
        WHERE c.invite_code = ? AND c.deleted_at IS NULL
    ");
    $stmt->execute([$inviteCode]);
    
    return $stmt->fetch();
}

/**
 * Get all courses (for admin)
 */
function getAllCourses($includeDeleted = false) {
    $pdo = getDBConnection();
    
    $sql = "
        SELECT c.*, u.name as faculty_name, u.email as faculty_email,
               (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'approved') as enrolled_count,
               (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'pending') as pending_count
        FROM courses c
        JOIN users u ON c.faculty_id = u.id
    ";
    
    if (!$includeDeleted) {
        $sql .= " WHERE c.deleted_at IS NULL";
    }
    
    $sql .= " ORDER BY c.created_at DESC";
    
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

/**
 * Get courses by faculty
 */
function getCoursesByFaculty($facultyId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT c.*,
               (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'approved') as enrolled_count,
               (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'pending') as pending_count
        FROM courses c
        WHERE c.faculty_id = ? AND c.deleted_at IS NULL
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$facultyId]);
    
    return $stmt->fetchAll();
}

/**
 * Get courses for student (enrolled)
 */
function getCoursesForStudent($studentId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT c.*, u.name as faculty_name, e.status as enrollment_status
        FROM courses c
        JOIN users u ON c.faculty_id = u.id
        JOIN enrollments e ON c.id = e.course_id
        WHERE e.student_id = ? AND c.deleted_at IS NULL
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$studentId]);
    
    return $stmt->fetchAll();
}

/**
 * Update course
 */
function updateCourse($courseId, $updates) {
    $pdo = getDBConnection();
    
    // Build update query
    $setClauses = [];
    $params = [];
    
    $allowedFields = ['name', 'code', 'description', 'thumbnail', 'header_image', 'start_date', 'end_date'];
    
    foreach ($updates as $field => $value) {
        if (in_array($field, $allowedFields)) {
            $setClauses[] = "{$field} = ?";
            $params[] = $value;
        }
    }
    
    if (empty($setClauses)) {
        return ['success' => false, 'message' => 'No valid updates provided'];
    }
    
    $setClauses[] = "updated_at = NOW()";
    $params[] = $courseId;
    
    $sql = "UPDATE courses SET " . implode(', ', $setClauses) . " WHERE id = ? AND deleted_at IS NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->rowCount() > 0) {
        return ['success' => true, 'message' => 'Course updated successfully'];
    }
    
    return ['success' => false, 'message' => 'Course not found or no changes made'];
}

/**
 * Delete course (soft delete)
 */
function deleteCourse($courseId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("UPDATE courses SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$courseId]);
    
    if ($stmt->rowCount() > 0) {
        return ['success' => true, 'message' => 'Course deleted successfully'];
    }
    
    return ['success' => false, 'message' => 'Course not found or already deleted'];
}

/**
 * Restore deleted course
 */
function restoreCourse($courseId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("UPDATE courses SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL");
    $stmt->execute([$courseId]);
    
    if ($stmt->rowCount() > 0) {
        return ['success' => true, 'message' => 'Course restored successfully'];
    }
    
    return ['success' => false, 'message' => 'Course not found or not deleted'];
}

/**
 * Regenerate invite code
 */
function regenerateInviteCode($courseId) {
    $pdo = getDBConnection();
    
    $newCode = generateInviteCode();
    
    $stmt = $pdo->prepare("UPDATE courses SET invite_code = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$newCode, $courseId]);
    
    if ($stmt->rowCount() > 0) {
        return [
            'success' => true,
            'message' => 'Invite code regenerated',
            'data' => [
                'invite_code' => $newCode,
                'invite_link' => APP_URL . '/public/register.html?invite=' . $newCode
            ]
        ];
    }
    
    return ['success' => false, 'message' => 'Course not found'];
}

/**
 * Get course statistics
 */
function getCourseStats($courseId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM enrollments WHERE course_id = ? AND status = 'approved') as enrolled_students,
            (SELECT COUNT(*) FROM enrollments WHERE course_id = ? AND status = 'pending') as pending_students,
            (SELECT COUNT(*) FROM weeks WHERE course_id = ?) as total_weeks,
            (SELECT COUNT(*) FROM lectures l JOIN weeks w ON l.week_id = w.id WHERE w.course_id = ?) as total_lectures,
            (SELECT COUNT(*) FROM concepts c JOIN lectures l ON c.lecture_id = l.id JOIN weeks w ON l.week_id = w.id WHERE w.course_id = ?) as total_concepts,
            (SELECT COUNT(*) FROM quizzes q JOIN concepts c ON q.concept_id = c.id JOIN lectures l ON c.lecture_id = l.id JOIN weeks w ON l.week_id = w.id WHERE w.course_id = ?) as total_quizzes
    ");
    $stmt->execute([$courseId, $courseId, $courseId, $courseId, $courseId, $courseId]);
    
    return $stmt->fetch();
}

/**
 * Get public course info (for guest view)
 */
function getPublicCourseInfo($courseId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT c.id, c.name, c.code, c.description, c.thumbnail, c.header_image, 
               c.start_date, c.end_date, u.name as faculty_name
        FROM courses c
        JOIN users u ON c.faculty_id = u.id
        WHERE c.id = ? AND c.deleted_at IS NULL
    ");
    $stmt->execute([$courseId]);
    
    return $stmt->fetch();
}

/**
 * Upload course image (thumbnail or header)
 */
function uploadCourseImage($courseId, $file, $type = 'thumbnail') {
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: JPEG, PNG, GIF, WEBP'];
    }
    
    // Validate file size (5MB max for images)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'File too large. Maximum 5MB allowed.'];
    }
    
    // Create directory if not exists
    $uploadDir = UPLOAD_DIR . 'courses/' . $courseId . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $type . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Update database
        $relativePath = 'uploads/courses/' . $courseId . '/' . $filename;
        $field = $type === 'thumbnail' ? 'thumbnail' : 'header_image';
        
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE courses SET {$field} = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$relativePath, $courseId]);
        
        return ['success' => true, 'message' => 'Image uploaded successfully', 'data' => ['path' => $relativePath]];
    }
    
    return ['success' => false, 'message' => 'Failed to upload image'];
}
