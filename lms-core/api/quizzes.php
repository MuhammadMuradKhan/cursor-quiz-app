<?php
/**
 * Quiz API Endpoint
 * 
 * Handles: Questions, Quizzes, Attempts management
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modules/auth/functions.php';
require_once __DIR__ . '/../modules/auth/middleware.php';
require_once __DIR__ . '/../modules/quiz/functions.php';
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
    // Questions
    case 'questions':
        handleGetQuestions($input);
        break;
    case 'create-question':
        handleCreateQuestion($input);
        break;
    case 'update-question':
        handleUpdateQuestion($input);
        break;
    case 'delete-question':
        handleDeleteQuestion($input);
        break;
    case 'toggle-question':
        handleToggleQuestion($input);
        break;
    
    // Quizzes
    case 'quizzes':
        handleGetQuizzes($input);
        break;
    case 'create-quiz':
        handleCreateQuiz($input);
        break;
    case 'update-quiz':
        handleUpdateQuiz($input);
        break;
    case 'delete-quiz':
        handleDeleteQuiz($input);
        break;
    case 'quiz-results':
        handleGetQuizResults($input);
        break;
    
    // Student Quiz Actions
    case 'available':
        handleGetAvailableQuizzes($input);
        break;
    case 'start':
        handleStartQuiz($input);
        break;
    case 'submit':
        handleSubmitQuiz($input);
        break;
    case 'my-results':
        handleGetMyResults($input);
        break;
    case 'my-history':
        handleGetMyHistory($input);
        break;
    
    // Leaderboards
    case 'concept-leaderboard':
        handleConceptLeaderboard($input);
        break;
    case 'course-leaderboard':
        handleCourseLeaderboard($input);
        break;
    
    default:
        jsonResponse(false, null, 'Invalid action');
}

// =====================================================
// QUESTIONS
// =====================================================

function handleGetQuestions($input) {
    requireFaculty();
    validateRequired($input, ['concept_id']);
    
    $conceptId = (int) $input['concept_id'];
    $activeOnly = !isset($input['include_inactive']) || $input['include_inactive'] !== 'true';
    
    // Check ownership
    $courseId = getConceptCourseId($conceptId);
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $questions = getQuestionsByConcept($conceptId, $activeOnly);
    jsonResponse(true, $questions, 'Questions retrieved');
}

function handleCreateQuestion($input) {
    requireFaculty();
    validateRequired($input, ['concept_id', 'type', 'content', 'options', 'correct_answer']);
    
    $conceptId = (int) $input['concept_id'];
    
    // Check ownership
    $courseId = getConceptCourseId($conceptId);
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $type = sanitize($input['type']);
    $content = $input['content']; // Allow HTML
    $options = $input['options'];
    $correctAnswer = (int) $input['correct_answer'];
    $questionText = isset($input['question_text']) ? $input['question_text'] : null;
    $difficulty = isset($input['difficulty']) ? sanitize($input['difficulty']) : 'medium';
    
    $result = createQuestion($conceptId, $type, $content, $options, $correctAnswer, $questionText, $difficulty);
    jsonResponse($result['success'], $result['data'] ?? null, $result['message']);
}

function handleUpdateQuestion($input) {
    requireFaculty();
    validateRequired($input, ['question_id']);
    
    $questionId = (int) $input['question_id'];
    
    // Check ownership
    $courseId = getQuestionCourseId($questionId);
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $updates = [];
    if (isset($input['type'])) $updates['type'] = sanitize($input['type']);
    if (isset($input['content'])) $updates['content'] = $input['content'];
    if (isset($input['question_text'])) $updates['question_text'] = $input['question_text'];
    if (isset($input['options'])) $updates['options'] = $input['options'];
    if (isset($input['correct_answer'])) $updates['correct_answer'] = (int) $input['correct_answer'];
    if (isset($input['difficulty'])) $updates['difficulty'] = sanitize($input['difficulty']);
    if (isset($input['is_active'])) $updates['is_active'] = $input['is_active'] ? 1 : 0;
    
    $result = updateQuestion($questionId, $updates);
    jsonResponse($result['success'], null, $result['message']);
}

function handleDeleteQuestion($input) {
    requireFaculty();
    validateRequired($input, ['question_id']);
    
    $questionId = (int) $input['question_id'];
    
    // Check ownership
    $courseId = getQuestionCourseId($questionId);
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $result = deleteQuestion($questionId);
    jsonResponse($result['success'], null, $result['message']);
}

function handleToggleQuestion($input) {
    requireFaculty();
    validateRequired($input, ['question_id']);
    
    $questionId = (int) $input['question_id'];
    
    // Check ownership
    $courseId = getQuestionCourseId($questionId);
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $result = toggleQuestionActive($questionId);
    jsonResponse($result['success'], null, $result['message']);
}

// =====================================================
// QUIZZES
// =====================================================

function handleGetQuizzes($input) {
    requireFaculty();
    validateRequired($input, ['concept_id']);
    
    $conceptId = (int) $input['concept_id'];
    
    // Check ownership
    $courseId = getConceptCourseId($conceptId);
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $quizzes = getQuizzesByConcept($conceptId);
    jsonResponse(true, $quizzes, 'Quizzes retrieved');
}

function handleCreateQuiz($input) {
    requireFaculty();
    validateRequired($input, ['concept_id', 'title', 'num_questions', 'start_time', 'end_time']);
    
    $conceptId = (int) $input['concept_id'];
    
    // Check ownership
    $courseId = getConceptCourseId($conceptId);
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $user = getCurrentUser();
    
    $title = sanitize($input['title']);
    $numQuestions = (int) $input['num_questions'];
    $startTime = sanitize($input['start_time']);
    $endTime = sanitize($input['end_time']);
    $durationMinutes = isset($input['duration_minutes']) ? (int) $input['duration_minutes'] : QUIZ_DEFAULT_DURATION;
    
    $result = createQuiz($conceptId, $title, $numQuestions, $startTime, $endTime, $user['id'], $durationMinutes);
    
    // Send notification emails to enrolled students
    if ($result['success']) {
        notifyStudentsOfQuiz($courseId, $title, $startTime, $endTime);
    }
    
    jsonResponse($result['success'], $result['data'] ?? null, $result['message']);
}

function notifyStudentsOfQuiz($courseId, $quizTitle, $startTime, $endTime) {
    require_once __DIR__ . '/../modules/enrollment/functions.php';
    require_once __DIR__ . '/../modules/email/functions.php';
    
    $enrollments = getEnrollmentsByCourse($courseId, 'approved');
    $course = getCourseById($courseId);
    
    foreach ($enrollments as $enrollment) {
        sendQuizNotificationEmail(
            $enrollment['student_email'],
            $enrollment['student_name'],
            $quizTitle,
            $course['name'],
            'New Quiz',
            $endTime
        );
    }
}

function handleUpdateQuiz($input) {
    requireFaculty();
    validateRequired($input, ['quiz_id']);
    
    $quizId = (int) $input['quiz_id'];
    
    // Check ownership
    $courseId = getQuizCourseId($quizId);
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $updates = [];
    if (isset($input['title'])) $updates['title'] = sanitize($input['title']);
    if (isset($input['num_questions'])) $updates['num_questions'] = (int) $input['num_questions'];
    if (isset($input['duration_minutes'])) $updates['duration_minutes'] = (int) $input['duration_minutes'];
    if (isset($input['start_time'])) $updates['start_time'] = sanitize($input['start_time']);
    if (isset($input['end_time'])) $updates['end_time'] = sanitize($input['end_time']);
    
    $result = updateQuiz($quizId, $updates);
    jsonResponse($result['success'], null, $result['message']);
}

function handleDeleteQuiz($input) {
    requireFaculty();
    validateRequired($input, ['quiz_id']);
    
    $quizId = (int) $input['quiz_id'];
    
    // Check ownership
    $courseId = getQuizCourseId($quizId);
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $result = deleteQuiz($quizId);
    jsonResponse($result['success'], null, $result['message']);
}

function handleGetQuizResults($input) {
    requireFaculty();
    validateRequired($input, ['quiz_id']);
    
    $quizId = (int) $input['quiz_id'];
    
    // Check ownership
    $courseId = getQuizCourseId($quizId);
    if (!isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $results = getQuizResultsForFaculty($quizId);
    $quiz = getQuizById($quizId);
    
    jsonResponse(true, [
        'quiz' => $quiz,
        'results' => $results
    ], 'Results retrieved');
}

// =====================================================
// STUDENT QUIZ ACTIONS
// =====================================================

function handleGetAvailableQuizzes($input) {
    requireStudent();
    validateRequired($input, ['course_id']);
    
    $user = getCurrentUser();
    $courseId = (int) $input['course_id'];
    
    // Check enrollment
    if (!isEnrolledInCourse($courseId)) {
        jsonResponse(false, null, 'Not enrolled in this course');
    }
    
    $quizzes = getAvailableQuizzesForStudent($user['id'], $courseId);
    jsonResponse(true, $quizzes, 'Available quizzes retrieved');
}

function handleStartQuiz($input) {
    requireStudent();
    validateRequired($input, ['quiz_id']);
    
    $user = getCurrentUser();
    $quizId = (int) $input['quiz_id'];
    
    // Check enrollment
    $courseId = getQuizCourseId($quizId);
    if (!isEnrolledInCourse($courseId)) {
        jsonResponse(false, null, 'Not enrolled in this course');
    }
    
    $result = startQuizAttempt($quizId, $user['id']);
    jsonResponse($result['success'], $result['data'] ?? null, $result['message'] ?? '');
}

function handleSubmitQuiz($input) {
    requireStudent();
    validateRequired($input, ['attempt_id', 'answers']);
    
    $attemptId = (int) $input['attempt_id'];
    $answers = $input['answers'];
    $isViolation = isset($input['is_violation']) && $input['is_violation'];
    $violationReason = isset($input['violation_reason']) ? sanitize($input['violation_reason']) : null;
    
    // Verify attempt belongs to current user
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT student_id FROM quiz_attempts WHERE id = ?");
    $stmt->execute([$attemptId]);
    $attempt = $stmt->fetch();
    
    $user = getCurrentUser();
    if (!$attempt || $attempt['student_id'] != $user['id']) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $result = submitQuizAttempt($attemptId, $answers, $isViolation, $violationReason);
    jsonResponse($result['success'], $result['data'] ?? null, $result['message']);
}

function handleGetMyResults($input) {
    requireStudent();
    validateRequired($input, ['attempt_id']);
    
    $attemptId = (int) $input['attempt_id'];
    
    // Verify attempt belongs to current user
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT student_id FROM quiz_attempts WHERE id = ?");
    $stmt->execute([$attemptId]);
    $attempt = $stmt->fetch();
    
    $user = getCurrentUser();
    if (!$attempt || $attempt['student_id'] != $user['id']) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $result = getQuizAttemptResults($attemptId);
    jsonResponse($result['success'], $result['data'] ?? null, $result['message'] ?? '');
}

function handleGetMyHistory($input) {
    requireStudent();
    
    $user = getCurrentUser();
    $courseId = isset($input['course_id']) ? (int) $input['course_id'] : null;
    
    $history = getStudentQuizHistory($user['id'], $courseId);
    jsonResponse(true, $history, 'Quiz history retrieved');
}

// =====================================================
// LEADERBOARDS
// =====================================================

function handleConceptLeaderboard($input) {
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
    
    $leaderboard = getConceptLeaderboard($conceptId);
    jsonResponse(true, $leaderboard, 'Leaderboard retrieved');
}

function handleCourseLeaderboard($input) {
    requireLogin();
    validateRequired($input, ['course_id']);
    
    $courseId = (int) $input['course_id'];
    $user = getCurrentUser();
    
    if ($user['role'] === 'student' && !isEnrolledInCourse($courseId)) {
        jsonResponse(false, null, 'Access denied');
    } elseif ($user['role'] === 'faculty' && !isCourseOwner($courseId)) {
        jsonResponse(false, null, 'Access denied');
    }
    
    $leaderboard = getCourseLeaderboard($courseId);
    jsonResponse(true, $leaderboard, 'Leaderboard retrieved');
}
