<?php
/**
 * Content Management Functions
 * Handles: Weeks, Lectures, Concepts, Materials
 */

require_once __DIR__ . '/../../config.php';

// =====================================================
// WEEKS FUNCTIONS
// =====================================================

/**
 * Get all weeks for a course
 */
function getWeeksByCourse($courseId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT w.*,
               (SELECT COUNT(*) FROM lectures l WHERE l.week_id = w.id) as lecture_count
        FROM weeks w
        WHERE w.course_id = ?
        ORDER BY w.week_number ASC
    ");
    $stmt->execute([$courseId]);
    
    return $stmt->fetchAll();
}

/**
 * Get week by ID
 */
function getWeekById($weekId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM weeks WHERE id = ?");
    $stmt->execute([$weekId]);
    
    return $stmt->fetch();
}

/**
 * Update week
 */
function updateWeek($weekId, $title) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("UPDATE weeks SET title = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$title, $weekId]);
    
    return ['success' => true, 'message' => 'Week updated successfully'];
}

/**
 * Get week's course ID
 */
function getWeekCourseId($weekId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT course_id FROM weeks WHERE id = ?");
    $stmt->execute([$weekId]);
    $result = $stmt->fetch();
    return $result ? $result['course_id'] : null;
}

// =====================================================
// LECTURES FUNCTIONS
// =====================================================

/**
 * Create lecture
 */
function createLecture($weekId, $title, $orderIndex = 0) {
    $pdo = getDBConnection();
    
    // Get max order if not specified
    if ($orderIndex === 0) {
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(order_index), 0) + 1 as next_order FROM lectures WHERE week_id = ?");
        $stmt->execute([$weekId]);
        $result = $stmt->fetch();
        $orderIndex = $result['next_order'];
    }
    
    $stmt = $pdo->prepare("INSERT INTO lectures (week_id, title, order_index, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$weekId, $title, $orderIndex]);
    
    return [
        'success' => true,
        'message' => 'Lecture created successfully',
        'data' => ['lecture_id' => $pdo->lastInsertId()]
    ];
}

/**
 * Get lectures by week
 */
function getLecturesByWeek($weekId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT l.*,
               (SELECT COUNT(*) FROM concepts c WHERE c.lecture_id = l.id) as concept_count
        FROM lectures l
        WHERE l.week_id = ?
        ORDER BY l.order_index ASC
    ");
    $stmt->execute([$weekId]);
    
    return $stmt->fetchAll();
}

/**
 * Get lecture by ID
 */
function getLectureById($lectureId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM lectures WHERE id = ?");
    $stmt->execute([$lectureId]);
    
    return $stmt->fetch();
}

/**
 * Update lecture
 */
function updateLecture($lectureId, $updates) {
    $pdo = getDBConnection();
    
    $setClauses = [];
    $params = [];
    
    if (isset($updates['title'])) {
        $setClauses[] = "title = ?";
        $params[] = $updates['title'];
    }
    if (isset($updates['order_index'])) {
        $setClauses[] = "order_index = ?";
        $params[] = $updates['order_index'];
    }
    
    if (empty($setClauses)) {
        return ['success' => false, 'message' => 'No updates provided'];
    }
    
    $setClauses[] = "updated_at = NOW()";
    $params[] = $lectureId;
    
    $sql = "UPDATE lectures SET " . implode(', ', $setClauses) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return ['success' => true, 'message' => 'Lecture updated successfully'];
}

/**
 * Delete lecture
 */
function deleteLecture($lectureId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("DELETE FROM lectures WHERE id = ?");
    $stmt->execute([$lectureId]);
    
    if ($stmt->rowCount() > 0) {
        return ['success' => true, 'message' => 'Lecture deleted successfully'];
    }
    
    return ['success' => false, 'message' => 'Lecture not found'];
}

/**
 * Get lecture's course ID
 */
function getLectureCourseId($lectureId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT w.course_id 
        FROM lectures l 
        JOIN weeks w ON l.week_id = w.id 
        WHERE l.id = ?
    ");
    $stmt->execute([$lectureId]);
    $result = $stmt->fetch();
    return $result ? $result['course_id'] : null;
}

// =====================================================
// CONCEPTS FUNCTIONS
// =====================================================

/**
 * Create concept
 */
function createConcept($lectureId, $title, $orderIndex = 0) {
    $pdo = getDBConnection();
    
    // Get max order if not specified
    if ($orderIndex === 0) {
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(order_index), 0) + 1 as next_order FROM concepts WHERE lecture_id = ?");
        $stmt->execute([$lectureId]);
        $result = $stmt->fetch();
        $orderIndex = $result['next_order'];
    }
    
    $stmt = $pdo->prepare("INSERT INTO concepts (lecture_id, title, order_index, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$lectureId, $title, $orderIndex]);
    
    return [
        'success' => true,
        'message' => 'Concept created successfully',
        'data' => ['concept_id' => $pdo->lastInsertId()]
    ];
}

/**
 * Get concepts by lecture
 */
function getConceptsByLecture($lectureId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT c.*,
               (SELECT COUNT(*) FROM materials m WHERE m.concept_id = c.id) as material_count,
               (SELECT COUNT(*) FROM questions q WHERE q.concept_id = c.id AND q.is_active = TRUE) as question_count,
               (SELECT COUNT(*) FROM quizzes qz WHERE qz.concept_id = c.id) as quiz_count
        FROM concepts c
        WHERE c.lecture_id = ?
        ORDER BY c.order_index ASC
    ");
    $stmt->execute([$lectureId]);
    
    return $stmt->fetchAll();
}

/**
 * Get concept by ID
 */
function getConceptById($conceptId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT c.*, l.title as lecture_title, l.week_id,
               w.title as week_title, w.week_number, w.course_id
        FROM concepts c
        JOIN lectures l ON c.lecture_id = l.id
        JOIN weeks w ON l.week_id = w.id
        WHERE c.id = ?
    ");
    $stmt->execute([$conceptId]);
    
    return $stmt->fetch();
}

/**
 * Update concept
 */
function updateConcept($conceptId, $updates) {
    $pdo = getDBConnection();
    
    $setClauses = [];
    $params = [];
    
    if (isset($updates['title'])) {
        $setClauses[] = "title = ?";
        $params[] = $updates['title'];
    }
    if (isset($updates['order_index'])) {
        $setClauses[] = "order_index = ?";
        $params[] = $updates['order_index'];
    }
    
    if (empty($setClauses)) {
        return ['success' => false, 'message' => 'No updates provided'];
    }
    
    $setClauses[] = "updated_at = NOW()";
    $params[] = $conceptId;
    
    $sql = "UPDATE concepts SET " . implode(', ', $setClauses) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return ['success' => true, 'message' => 'Concept updated successfully'];
}

/**
 * Delete concept
 */
function deleteConcept($conceptId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("DELETE FROM concepts WHERE id = ?");
    $stmt->execute([$conceptId]);
    
    if ($stmt->rowCount() > 0) {
        return ['success' => true, 'message' => 'Concept deleted successfully'];
    }
    
    return ['success' => false, 'message' => 'Concept not found'];
}

/**
 * Get concept's course ID
 */
function getConceptCourseId($conceptId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT w.course_id 
        FROM concepts c 
        JOIN lectures l ON c.lecture_id = l.id 
        JOIN weeks w ON l.week_id = w.id 
        WHERE c.id = ?
    ");
    $stmt->execute([$conceptId]);
    $result = $stmt->fetch();
    return $result ? $result['course_id'] : null;
}

// =====================================================
// MATERIALS FUNCTIONS
// =====================================================

/**
 * Create material
 */
function createMaterial($conceptId, $type, $title, $filePath = null, $content = null, $externalUrl = null) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        INSERT INTO materials (concept_id, type, title, file_path, content, external_url, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$conceptId, $type, $title, $filePath, $content, $externalUrl]);
    
    return [
        'success' => true,
        'message' => 'Material created successfully',
        'data' => ['material_id' => $pdo->lastInsertId()]
    ];
}

/**
 * Get materials by concept
 */
function getMaterialsByConcept($conceptId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM materials WHERE concept_id = ? ORDER BY created_at ASC");
    $stmt->execute([$conceptId]);
    
    return $stmt->fetchAll();
}

/**
 * Get material by ID
 */
function getMaterialById($materialId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM materials WHERE id = ?");
    $stmt->execute([$materialId]);
    
    return $stmt->fetch();
}

/**
 * Update material
 */
function updateMaterial($materialId, $updates) {
    $pdo = getDBConnection();
    
    $setClauses = [];
    $params = [];
    
    $allowedFields = ['title', 'type', 'content', 'external_url'];
    
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
    $params[] = $materialId;
    
    $sql = "UPDATE materials SET " . implode(', ', $setClauses) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return ['success' => true, 'message' => 'Material updated successfully'];
}

/**
 * Delete material
 */
function deleteMaterial($materialId) {
    $pdo = getDBConnection();
    
    // Get file path before deletion
    $stmt = $pdo->prepare("SELECT file_path FROM materials WHERE id = ?");
    $stmt->execute([$materialId]);
    $material = $stmt->fetch();
    
    // Delete from database
    $stmt = $pdo->prepare("DELETE FROM materials WHERE id = ?");
    $stmt->execute([$materialId]);
    
    if ($stmt->rowCount() > 0) {
        // Delete file if exists
        if ($material && $material['file_path']) {
            $fullPath = __DIR__ . '/../../' . $material['file_path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
        return ['success' => true, 'message' => 'Material deleted successfully'];
    }
    
    return ['success' => false, 'message' => 'Material not found'];
}

/**
 * Upload material file
 */
function uploadMaterial($conceptId, $file, $title, $type = null) {
    // Validate file size
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return ['success' => false, 'message' => 'File too large. Maximum ' . (UPLOAD_MAX_SIZE / 1024 / 1024) . 'MB allowed.'];
    }
    
    // Determine type from extension if not provided
    if (!$type) {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $typeMap = [
            'pdf' => 'pdf',
            'ppt' => 'ppt',
            'pptx' => 'ppt',
            'mp4' => 'video',
            'webm' => 'video',
            'zip' => 'zip',
            'html' => 'html',
            'htm' => 'html'
        ];
        $type = $typeMap[$extension] ?? 'other';
    }
    
    // Get concept info to create path
    $concept = getConceptById($conceptId);
    if (!$concept) {
        return ['success' => false, 'message' => 'Concept not found'];
    }
    
    // Create directory structure
    $uploadDir = UPLOAD_DIR . 'courses/' . $concept['course_id'] . '/materials/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'material_' . $conceptId . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $relativePath = 'uploads/courses/' . $concept['course_id'] . '/materials/' . $filename;
        
        // Create material record
        return createMaterial($conceptId, $type, $title, $relativePath);
    }
    
    return ['success' => false, 'message' => 'Failed to upload file'];
}

/**
 * Get material's course ID
 */
function getMaterialCourseId($materialId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT w.course_id 
        FROM materials m
        JOIN concepts c ON m.concept_id = c.id 
        JOIN lectures l ON c.lecture_id = l.id 
        JOIN weeks w ON l.week_id = w.id 
        WHERE m.id = ?
    ");
    $stmt->execute([$materialId]);
    $result = $stmt->fetch();
    return $result ? $result['course_id'] : null;
}

// =====================================================
// HIERARCHICAL DATA FUNCTIONS
// =====================================================

/**
 * Get full course content hierarchy
 */
function getCourseContentHierarchy($courseId) {
    $weeks = getWeeksByCourse($courseId);
    
    foreach ($weeks as &$week) {
        $week['lectures'] = getLecturesByWeek($week['id']);
        
        foreach ($week['lectures'] as &$lecture) {
            $lecture['concepts'] = getConceptsByLecture($lecture['id']);
            
            foreach ($lecture['concepts'] as &$concept) {
                $concept['materials'] = getMaterialsByConcept($concept['id']);
            }
        }
    }
    
    return $weeks;
}

/**
 * Get course content hierarchy for student (only approved)
 */
function getCourseContentForStudent($courseId, $studentId) {
    // Check if student is enrolled
    require_once __DIR__ . '/../enrollment/functions.php';
    if (!isStudentEnrolled($studentId, $courseId)) {
        return null;
    }
    
    return getCourseContentHierarchy($courseId);
}
