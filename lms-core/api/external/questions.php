<?php
/**
 * External Questions API
 * 
 * For third-party systems to insert questions
 * Requires Master API Key authentication
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../modules/auth/middleware.php';
require_once __DIR__ . '/../../modules/quiz/functions.php';
require_once __DIR__ . '/../../modules/content/functions.php';

// Set headers
setCORSHeaders();
header('Content-Type: application/json');

// Require API key for all requests
requireApiKey();

// Get action from request
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Get input data
$input = getJsonInput();
if (empty($input) && $method === 'POST') {
    $input = $_POST;
}

switch ($action) {
    case 'insert':
        handleInsertQuestions($input);
        break;
        
    case 'bulk-insert':
        handleBulkInsertQuestions($input);
        break;
        
    case 'concepts':
        handleGetConcepts($input);
        break;
        
    default:
        jsonResponse(false, null, 'Invalid action. Available actions: insert, bulk-insert, concepts');
}

/**
 * Insert single question
 */
function handleInsertQuestions($input) {
    validateRequired($input, ['concept_id', 'type', 'content', 'options', 'correctAnswer']);
    
    $conceptId = (int) $input['concept_id'];
    
    // Verify concept exists
    $concept = getConceptById($conceptId);
    if (!$concept) {
        jsonResponse(false, null, 'Concept not found');
    }
    
    $type = sanitize($input['type']);
    $content = $input['content'];
    $options = $input['options'];
    $correctAnswer = (int) $input['correctAnswer'];
    $questionText = isset($input['questionText']) ? $input['questionText'] : null;
    $difficulty = isset($input['difficulty']) ? sanitize($input['difficulty']) : 'medium';
    
    $result = createQuestion($conceptId, $type, $content, $options, $correctAnswer, $questionText, $difficulty);
    jsonResponse($result['success'], $result['data'] ?? null, $result['message']);
}

/**
 * Bulk insert questions
 * 
 * Expected format:
 * {
 *   "concept_id": 1,
 *   "questions": [
 *     {
 *       "type": "text",
 *       "content": "Question text",
 *       "questionText": "Optional additional text",
 *       "options": [
 *         {"id": 0, "type": "text", "content": "Option A"},
 *         {"id": 1, "type": "text", "content": "Option B"},
 *         {"id": 2, "type": "text", "content": "Option C"},
 *         {"id": 3, "type": "text", "content": "Option D"}
 *       ],
 *       "correctAnswer": 1,
 *       "difficulty": "medium"
 *     }
 *   ]
 * }
 */
function handleBulkInsertQuestions($input) {
    validateRequired($input, ['concept_id', 'questions']);
    
    $conceptId = (int) $input['concept_id'];
    $questions = $input['questions'];
    
    // Verify concept exists
    $concept = getConceptById($conceptId);
    if (!$concept) {
        jsonResponse(false, null, 'Concept not found');
    }
    
    if (!is_array($questions) || empty($questions)) {
        jsonResponse(false, null, 'Questions must be a non-empty array');
    }
    
    $result = bulkInsertQuestions($conceptId, $questions);
    jsonResponse($result['success'], $result['data'] ?? null, $result['message']);
}

/**
 * Get available concepts for a course (to help external systems)
 */
function handleGetConcepts($input) {
    validateRequired($input, ['course_id']);
    
    $courseId = (int) $input['course_id'];
    
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT c.id, c.title as concept_title, 
               l.title as lecture_title, 
               w.week_number, w.title as week_title
        FROM concepts c
        JOIN lectures l ON c.lecture_id = l.id
        JOIN weeks w ON l.week_id = w.id
        WHERE w.course_id = ?
        ORDER BY w.week_number, l.order_index, c.order_index
    ");
    $stmt->execute([$courseId]);
    
    $concepts = $stmt->fetchAll();
    jsonResponse(true, $concepts, 'Concepts retrieved');
}
