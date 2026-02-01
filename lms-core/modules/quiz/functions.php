<?php
/**
 * Quiz Management Functions
 * Handles: Questions, Quizzes, Attempts, Grading
 */

require_once __DIR__ . '/../../config.php';

// =====================================================
// QUESTIONS FUNCTIONS
// =====================================================

/**
 * Create question
 */
function createQuestion($conceptId, $type, $content, $options, $correctAnswer, $questionText = null, $difficulty = 'medium') {
    $pdo = getDBConnection();
    
    // Validate options JSON
    if (is_array($options)) {
        $options = json_encode($options);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO questions (concept_id, type, content, question_text, options, correct_answer, difficulty, is_active, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, TRUE, NOW())
    ");
    $stmt->execute([$conceptId, $type, $content, $questionText, $options, $correctAnswer, $difficulty]);
    
    return [
        'success' => true,
        'message' => 'Question created successfully',
        'data' => ['question_id' => $pdo->lastInsertId()]
    ];
}

/**
 * Get questions by concept
 */
function getQuestionsByConcept($conceptId, $activeOnly = true) {
    $pdo = getDBConnection();
    
    $sql = "SELECT * FROM questions WHERE concept_id = ?";
    if ($activeOnly) {
        $sql .= " AND is_active = TRUE";
    }
    $sql .= " ORDER BY created_at ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$conceptId]);
    
    $questions = $stmt->fetchAll();
    
    // Decode options JSON
    foreach ($questions as &$q) {
        $q['options'] = json_decode($q['options'], true);
    }
    
    return $questions;
}

/**
 * Get question by ID
 */
function getQuestionById($questionId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE id = ?");
    $stmt->execute([$questionId]);
    
    $question = $stmt->fetch();
    if ($question) {
        $question['options'] = json_decode($question['options'], true);
    }
    
    return $question;
}

/**
 * Update question
 */
function updateQuestion($questionId, $updates) {
    $pdo = getDBConnection();
    
    $setClauses = [];
    $params = [];
    
    $allowedFields = ['type', 'content', 'question_text', 'correct_answer', 'difficulty', 'is_active'];
    
    foreach ($updates as $field => $value) {
        if (in_array($field, $allowedFields)) {
            $setClauses[] = "{$field} = ?";
            $params[] = $value;
        }
    }
    
    // Handle options separately (needs JSON encoding)
    if (isset($updates['options'])) {
        $setClauses[] = "options = ?";
        $params[] = is_array($updates['options']) ? json_encode($updates['options']) : $updates['options'];
    }
    
    if (empty($setClauses)) {
        return ['success' => false, 'message' => 'No valid updates provided'];
    }
    
    $setClauses[] = "updated_at = NOW()";
    $params[] = $questionId;
    
    $sql = "UPDATE questions SET " . implode(', ', $setClauses) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return ['success' => true, 'message' => 'Question updated successfully'];
}

/**
 * Delete question
 */
function deleteQuestion($questionId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
    $stmt->execute([$questionId]);
    
    if ($stmt->rowCount() > 0) {
        return ['success' => true, 'message' => 'Question deleted successfully'];
    }
    
    return ['success' => false, 'message' => 'Question not found'];
}

/**
 * Toggle question active status
 */
function toggleQuestionActive($questionId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("UPDATE questions SET is_active = NOT is_active, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$questionId]);
    
    return ['success' => true, 'message' => 'Question status toggled'];
}

/**
 * Get question's course ID
 */
function getQuestionCourseId($questionId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT w.course_id 
        FROM questions q
        JOIN concepts c ON q.concept_id = c.id 
        JOIN lectures l ON c.lecture_id = l.id 
        JOIN weeks w ON l.week_id = w.id 
        WHERE q.id = ?
    ");
    $stmt->execute([$questionId]);
    $result = $stmt->fetch();
    return $result ? $result['course_id'] : null;
}

/**
 * Bulk insert questions (for external API)
 */
function bulkInsertQuestions($conceptId, $questions) {
    $pdo = getDBConnection();
    $inserted = 0;
    
    foreach ($questions as $q) {
        $type = $q['type'] ?? 'text';
        $content = $q['content'] ?? '';
        $questionText = $q['questionText'] ?? null;
        $options = isset($q['options']) ? json_encode($q['options']) : '[]';
        $correctAnswer = $q['correctAnswer'] ?? 0;
        $difficulty = $q['difficulty'] ?? 'medium';
        
        $stmt = $pdo->prepare("
            INSERT INTO questions (concept_id, type, content, question_text, options, correct_answer, difficulty, is_active, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, TRUE, NOW())
        ");
        $stmt->execute([$conceptId, $type, $content, $questionText, $options, $correctAnswer, $difficulty]);
        $inserted++;
    }
    
    return [
        'success' => true,
        'message' => "{$inserted} question(s) inserted successfully",
        'data' => ['inserted_count' => $inserted]
    ];
}

// =====================================================
// QUIZZES FUNCTIONS
// =====================================================

/**
 * Create quiz
 */
function createQuiz($conceptId, $title, $numQuestions, $startTime, $endTime, $createdBy, $durationMinutes = 10) {
    $pdo = getDBConnection();
    
    // Check if there are enough active questions
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM questions WHERE concept_id = ? AND is_active = TRUE");
    $stmt->execute([$conceptId]);
    $questionCount = $stmt->fetch()['count'];
    
    if ($questionCount < $numQuestions) {
        return [
            'success' => false,
            'message' => "Not enough questions. Available: {$questionCount}, Requested: {$numQuestions}"
        ];
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO quizzes (concept_id, title, num_questions, duration_minutes, start_time, end_time, created_by, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$conceptId, $title, $numQuestions, $durationMinutes, $startTime, $endTime, $createdBy]);
    
    return [
        'success' => true,
        'message' => 'Quiz created successfully',
        'data' => ['quiz_id' => $pdo->lastInsertId()]
    ];
}

/**
 * Get quizzes by concept
 */
function getQuizzesByConcept($conceptId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT q.*, 
               (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id = q.id) as attempt_count,
               (SELECT AVG(qa.score) FROM quiz_attempts qa WHERE qa.quiz_id = q.id AND qa.submitted_at IS NOT NULL) as avg_score
        FROM quizzes q
        WHERE q.concept_id = ?
        ORDER BY q.created_at DESC
    ");
    $stmt->execute([$conceptId]);
    
    return $stmt->fetchAll();
}

/**
 * Get quiz by ID
 */
function getQuizById($quizId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT q.*, c.title as concept_title, c.lecture_id,
               l.title as lecture_title, l.week_id,
               w.title as week_title, w.course_id
        FROM quizzes q
        JOIN concepts c ON q.concept_id = c.id
        JOIN lectures l ON c.lecture_id = l.id
        JOIN weeks w ON l.week_id = w.id
        WHERE q.id = ?
    ");
    $stmt->execute([$quizId]);
    
    return $stmt->fetch();
}

/**
 * Get available quizzes for student in a course
 */
function getAvailableQuizzesForStudent($studentId, $courseId) {
    $pdo = getDBConnection();
    
    $now = date('Y-m-d H:i:s');
    
    $stmt = $pdo->prepare("
        SELECT q.*, c.title as concept_title, l.title as lecture_title, w.week_number,
               (SELECT id FROM quiz_attempts WHERE quiz_id = q.id AND student_id = ?) as attempt_id,
               (SELECT submitted_at FROM quiz_attempts WHERE quiz_id = q.id AND student_id = ?) as submitted_at
        FROM quizzes q
        JOIN concepts c ON q.concept_id = c.id
        JOIN lectures l ON c.lecture_id = l.id
        JOIN weeks w ON l.week_id = w.id
        WHERE w.course_id = ?
        AND q.start_time <= ?
        AND q.end_time >= ?
        ORDER BY q.start_time ASC
    ");
    $stmt->execute([$studentId, $studentId, $courseId, $now, $now]);
    
    return $stmt->fetchAll();
}

/**
 * Update quiz
 */
function updateQuiz($quizId, $updates) {
    $pdo = getDBConnection();
    
    $setClauses = [];
    $params = [];
    
    $allowedFields = ['title', 'num_questions', 'duration_minutes', 'start_time', 'end_time'];
    
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
    $params[] = $quizId;
    
    $sql = "UPDATE quizzes SET " . implode(', ', $setClauses) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return ['success' => true, 'message' => 'Quiz updated successfully'];
}

/**
 * Delete quiz
 */
function deleteQuiz($quizId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("DELETE FROM quizzes WHERE id = ?");
    $stmt->execute([$quizId]);
    
    if ($stmt->rowCount() > 0) {
        return ['success' => true, 'message' => 'Quiz deleted successfully'];
    }
    
    return ['success' => false, 'message' => 'Quiz not found'];
}

/**
 * Get quiz's course ID
 */
function getQuizCourseId($quizId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT w.course_id 
        FROM quizzes q
        JOIN concepts c ON q.concept_id = c.id 
        JOIN lectures l ON c.lecture_id = l.id 
        JOIN weeks w ON l.week_id = w.id 
        WHERE q.id = ?
    ");
    $stmt->execute([$quizId]);
    $result = $stmt->fetch();
    return $result ? $result['course_id'] : null;
}

// =====================================================
// QUIZ ATTEMPTS FUNCTIONS
// =====================================================

/**
 * Start quiz attempt
 */
function startQuizAttempt($quizId, $studentId) {
    $pdo = getDBConnection();
    
    // Get quiz details
    $quiz = getQuizById($quizId);
    if (!$quiz) {
        return ['success' => false, 'message' => 'Quiz not found'];
    }
    
    // Check if quiz is available
    $now = time();
    $start = strtotime($quiz['start_time']);
    $end = strtotime($quiz['end_time']);
    
    if ($now < $start) {
        return ['success' => false, 'message' => 'Quiz has not started yet'];
    }
    
    if ($now > $end) {
        return ['success' => false, 'message' => 'Quiz has ended'];
    }
    
    // Check if already attempted
    $stmt = $pdo->prepare("SELECT id, submitted_at FROM quiz_attempts WHERE quiz_id = ? AND student_id = ?");
    $stmt->execute([$quizId, $studentId]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        if ($existing['submitted_at']) {
            return ['success' => false, 'message' => 'You have already completed this quiz'];
        }
        // Return existing attempt
        return getQuizAttemptQuestions($existing['id']);
    }
    
    // Get random questions for this attempt
    $stmt = $pdo->prepare("
        SELECT id FROM questions 
        WHERE concept_id = ? AND is_active = TRUE 
        ORDER BY RAND() 
        LIMIT ?
    ");
    $stmt->execute([$quiz['concept_id'], $quiz['num_questions']]);
    $questionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Create attempt
    $stmt = $pdo->prepare("
        INSERT INTO quiz_attempts (quiz_id, student_id, question_ids, max_score, started_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$quizId, $studentId, json_encode($questionIds), count($questionIds)]);
    $attemptId = $pdo->lastInsertId();
    
    return getQuizAttemptQuestions($attemptId);
}

/**
 * Get quiz attempt with questions (for student view)
 */
function getQuizAttemptQuestions($attemptId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT qa.*, q.duration_minutes, q.title as quiz_title
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.id
        WHERE qa.id = ?
    ");
    $stmt->execute([$attemptId]);
    $attempt = $stmt->fetch();
    
    if (!$attempt) {
        return ['success' => false, 'message' => 'Attempt not found'];
    }
    
    // Get questions (without correct answers)
    $questionIds = json_decode($attempt['question_ids'], true);
    
    if (empty($questionIds)) {
        return ['success' => false, 'message' => 'No questions found'];
    }
    
    $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
    $stmt = $pdo->prepare("SELECT id, type, content, question_text, options FROM questions WHERE id IN ({$placeholders})");
    $stmt->execute($questionIds);
    $questions = $stmt->fetchAll();
    
    // Decode options and maintain order
    $orderedQuestions = [];
    foreach ($questionIds as $qId) {
        foreach ($questions as $q) {
            if ($q['id'] == $qId) {
                $q['options'] = json_decode($q['options'], true);
                $orderedQuestions[] = $q;
                break;
            }
        }
    }
    
    // Calculate time remaining
    $startedAt = strtotime($attempt['started_at']);
    $duration = $attempt['duration_minutes'] * 60;
    $elapsed = time() - $startedAt;
    $remaining = max(0, $duration - $elapsed);
    
    return [
        'success' => true,
        'data' => [
            'attempt_id' => $attempt['id'],
            'quiz_id' => $attempt['quiz_id'],
            'quiz_title' => $attempt['quiz_title'],
            'questions' => $orderedQuestions,
            'started_at' => $attempt['started_at'],
            'time_remaining_seconds' => $remaining,
            'duration_minutes' => $attempt['duration_minutes']
        ]
    ];
}

/**
 * Submit quiz attempt
 */
function submitQuizAttempt($attemptId, $answers, $isViolation = false, $violationReason = null) {
    $pdo = getDBConnection();
    
    // Get attempt
    $stmt = $pdo->prepare("SELECT * FROM quiz_attempts WHERE id = ?");
    $stmt->execute([$attemptId]);
    $attempt = $stmt->fetch();
    
    if (!$attempt) {
        return ['success' => false, 'message' => 'Attempt not found'];
    }
    
    if ($attempt['submitted_at']) {
        return ['success' => false, 'message' => 'Quiz already submitted'];
    }
    
    $questionIds = json_decode($attempt['question_ids'], true);
    $score = 0;
    
    // If violation, score is 0
    if (!$isViolation) {
        // Calculate score
        foreach ($questionIds as $index => $qId) {
            $stmt = $pdo->prepare("SELECT correct_answer FROM questions WHERE id = ?");
            $stmt->execute([$qId]);
            $question = $stmt->fetch();
            
            if ($question && isset($answers[$index]) && $answers[$index] == $question['correct_answer']) {
                $score++;
            }
        }
    }
    
    // Update attempt
    $stmt = $pdo->prepare("
        UPDATE quiz_attempts 
        SET answers = ?, score = ?, submitted_at = NOW(), is_violation = ?, violation_reason = ?
        WHERE id = ?
    ");
    $stmt->execute([
        json_encode($answers),
        $score,
        $isViolation ? 1 : 0,
        $violationReason,
        $attemptId
    ]);
    
    // Get student email for notification
    $stmt = $pdo->prepare("
        SELECT u.email, u.name, q.title as quiz_title
        FROM quiz_attempts qa
        JOIN users u ON qa.student_id = u.id
        JOIN quizzes q ON qa.quiz_id = q.id
        WHERE qa.id = ?
    ");
    $stmt->execute([$attemptId]);
    $info = $stmt->fetch();
    
    // Send results email
    if ($info) {
        require_once __DIR__ . '/../email/functions.php';
        sendQuizResultsEmail($info['email'], $info['name'], $info['quiz_title'], $score, $attempt['max_score']);
    }
    
    return [
        'success' => true,
        'message' => $isViolation ? 'Quiz submitted with violation (zero marks)' : 'Quiz submitted successfully',
        'data' => [
            'score' => $score,
            'max_score' => $attempt['max_score'],
            'is_violation' => $isViolation
        ]
    ];
}

/**
 * Get quiz attempt results (with correct answers)
 */
function getQuizAttemptResults($attemptId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT qa.*, q.title as quiz_title, c.title as concept_title
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.id
        JOIN concepts c ON q.concept_id = c.id
        WHERE qa.id = ?
    ");
    $stmt->execute([$attemptId]);
    $attempt = $stmt->fetch();
    
    if (!$attempt) {
        return ['success' => false, 'message' => 'Attempt not found'];
    }
    
    if (!$attempt['submitted_at']) {
        return ['success' => false, 'message' => 'Quiz not yet submitted'];
    }
    
    // Get questions with correct answers
    $questionIds = json_decode($attempt['question_ids'], true);
    $answers = json_decode($attempt['answers'], true) ?? [];
    
    $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE id IN ({$placeholders})");
    $stmt->execute($questionIds);
    $questions = $stmt->fetchAll();
    
    // Build results
    $results = [];
    foreach ($questionIds as $index => $qId) {
        foreach ($questions as $q) {
            if ($q['id'] == $qId) {
                $studentAnswer = $answers[$index] ?? null;
                $results[] = [
                    'question' => [
                        'id' => $q['id'],
                        'type' => $q['type'],
                        'content' => $q['content'],
                        'question_text' => $q['question_text'],
                        'options' => json_decode($q['options'], true)
                    ],
                    'correct_answer' => (int) $q['correct_answer'],
                    'student_answer' => $studentAnswer !== null ? (int) $studentAnswer : null,
                    'is_correct' => $studentAnswer !== null && (int) $studentAnswer === (int) $q['correct_answer']
                ];
                break;
            }
        }
    }
    
    return [
        'success' => true,
        'data' => [
            'attempt_id' => $attempt['id'],
            'quiz_title' => $attempt['quiz_title'],
            'concept_title' => $attempt['concept_title'],
            'score' => $attempt['score'],
            'max_score' => $attempt['max_score'],
            'percentage' => $attempt['max_score'] > 0 ? round(($attempt['score'] / $attempt['max_score']) * 100, 1) : 0,
            'started_at' => $attempt['started_at'],
            'submitted_at' => $attempt['submitted_at'],
            'is_violation' => (bool) $attempt['is_violation'],
            'violation_reason' => $attempt['violation_reason'],
            'results' => $results
        ]
    ];
}

/**
 * Get quiz results for faculty (all students)
 */
function getQuizResultsForFaculty($quizId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT qa.*, u.name as student_name, u.email as student_email, u.roll_no
        FROM quiz_attempts qa
        JOIN users u ON qa.student_id = u.id
        WHERE qa.quiz_id = ?
        ORDER BY qa.score DESC, qa.submitted_at ASC
    ");
    $stmt->execute([$quizId]);
    
    return $stmt->fetchAll();
}

/**
 * Get student quiz history
 */
function getStudentQuizHistory($studentId, $courseId = null) {
    $pdo = getDBConnection();
    
    $sql = "
        SELECT qa.*, q.title as quiz_title, c.title as concept_title, 
               w.course_id, co.name as course_name
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id = q.id
        JOIN concepts c ON q.concept_id = c.id
        JOIN lectures l ON c.lecture_id = l.id
        JOIN weeks w ON l.week_id = w.id
        JOIN courses co ON w.course_id = co.id
        WHERE qa.student_id = ?
    ";
    $params = [$studentId];
    
    if ($courseId) {
        $sql .= " AND w.course_id = ?";
        $params[] = $courseId;
    }
    
    $sql .= " ORDER BY qa.submitted_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Get leaderboard for a concept
 */
function getConceptLeaderboard($conceptId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT u.name, u.roll_no, 
               AVG(qa.score) as avg_score, 
               SUM(qa.score) as total_score,
               COUNT(qa.id) as quizzes_taken,
               SUM(CASE WHEN qa.is_violation = 1 THEN 1 ELSE 0 END) as violations
        FROM quiz_attempts qa
        JOIN users u ON qa.student_id = u.id
        JOIN quizzes q ON qa.quiz_id = q.id
        WHERE q.concept_id = ? AND qa.submitted_at IS NOT NULL
        GROUP BY qa.student_id
        ORDER BY avg_score DESC, total_score DESC
    ");
    $stmt->execute([$conceptId]);
    
    return $stmt->fetchAll();
}

/**
 * Get course leaderboard
 */
function getCourseLeaderboard($courseId) {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT u.name, u.roll_no, 
               AVG(qa.score) as avg_score, 
               SUM(qa.score) as total_score,
               SUM(qa.max_score) as total_max_score,
               COUNT(qa.id) as quizzes_taken,
               SUM(CASE WHEN qa.is_violation = 1 THEN 1 ELSE 0 END) as violations
        FROM quiz_attempts qa
        JOIN users u ON qa.student_id = u.id
        JOIN quizzes q ON qa.quiz_id = q.id
        JOIN concepts c ON q.concept_id = c.id
        JOIN lectures l ON c.lecture_id = l.id
        JOIN weeks w ON l.week_id = w.id
        WHERE w.course_id = ? AND qa.submitted_at IS NOT NULL
        GROUP BY qa.student_id
        ORDER BY total_score DESC, avg_score DESC
    ");
    $stmt->execute([$courseId]);
    
    return $stmt->fetchAll();
}
