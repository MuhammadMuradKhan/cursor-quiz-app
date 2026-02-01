<?php
/**
 * Setup Test Data Script
 * Creates quizzes for all concepts so students can test
 * 
 * DELETE THIS FILE AFTER USE!
 */

require_once 'config.php';

echo "<!DOCTYPE html><html><head><title>Setup Test Data</title>";
echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:50px auto;padding:20px;}";
echo ".success{background:#d4edda;border:1px solid #c3e6cb;padding:15px;border-radius:5px;margin:10px 0;}";
echo ".info{background:#d1ecf1;border:1px solid #bee5eb;padding:15px;border-radius:5px;margin:10px 0;}";
echo ".warning{background:#fff3cd;border:1px solid #ffeeba;padding:15px;border-radius:5px;margin:10px 0;}";
echo "h2{margin-top:30px;border-bottom:2px solid #eee;padding-bottom:10px;}";
echo "</style></head><body>";

echo "<h1>LMS Test Data Setup</h1>";

try {
    $pdo = getDBConnection();
    
    // Get faculty user
    $stmt = $pdo->query("SELECT id FROM users WHERE role = 'faculty' LIMIT 1");
    $faculty = $stmt->fetch();
    $facultyId = $faculty['id'];
    
    // Get student user (student1@test.com)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute(['student1@test.com']);
    $student = $stmt->fetch();
    $studentId = $student['id'];
    
    echo "<div class='info'>Faculty ID: {$facultyId}, Student ID: {$studentId}</div>";
    
    // =====================================================
    // STEP 1: Make sure student is enrolled in all courses
    // =====================================================
    echo "<h2>Step 1: Enrolling Student in All Courses</h2>";
    
    $stmt = $pdo->query("SELECT id, name FROM courses WHERE deleted_at IS NULL");
    $courses = $stmt->fetchAll();
    
    foreach ($courses as $course) {
        // Check if already enrolled
        $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE course_id = ? AND student_id = ?");
        $stmt->execute([$course['id'], $studentId]);
        
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO enrollments (course_id, student_id, status, created_at) VALUES (?, ?, 'approved', NOW())");
            $stmt->execute([$course['id'], $studentId]);
            echo "<p>✓ Enrolled in: {$course['name']}</p>";
        } else {
            // Make sure it's approved
            $stmt = $pdo->prepare("UPDATE enrollments SET status = 'approved' WHERE course_id = ? AND student_id = ?");
            $stmt->execute([$course['id'], $studentId]);
            echo "<p>✓ Already enrolled in: {$course['name']} (approved)</p>";
        }
    }
    
    // =====================================================
    // STEP 2: Add more questions to concepts
    // =====================================================
    echo "<h2>Step 2: Adding Questions to Concepts</h2>";
    
    // Get all concepts
    $stmt = $pdo->query("
        SELECT c.id, c.title as concept_title, l.title as lecture_title, w.week_number, co.name as course_name
        FROM concepts c
        JOIN lectures l ON c.lecture_id = l.id
        JOIN weeks w ON l.week_id = w.id
        JOIN courses co ON w.course_id = co.id
        WHERE co.deleted_at IS NULL
    ");
    $concepts = $stmt->fetchAll();
    
    $sampleQuestions = [
        [
            'type' => 'text',
            'content' => 'What is the primary purpose of this concept?',
            'options' => json_encode([
                ['id' => 0, 'type' => 'text', 'content' => 'To confuse students'],
                ['id' => 1, 'type' => 'text', 'content' => 'To teach fundamental principles'],
                ['id' => 2, 'type' => 'text', 'content' => 'To waste time'],
                ['id' => 3, 'type' => 'text', 'content' => 'None of the above']
            ]),
            'correct_answer' => 1,
            'difficulty' => 'low'
        ],
        [
            'type' => 'text',
            'content' => 'Which of the following best describes the key learning outcome?',
            'options' => json_encode([
                ['id' => 0, 'type' => 'text', 'content' => 'Understanding theoretical foundations'],
                ['id' => 1, 'type' => 'text', 'content' => 'Memorizing random facts'],
                ['id' => 2, 'type' => 'text', 'content' => 'Copying from textbooks'],
                ['id' => 3, 'type' => 'text', 'content' => 'Avoiding practical applications']
            ]),
            'correct_answer' => 0,
            'difficulty' => 'medium'
        ],
        [
            'type' => 'text',
            'content' => 'In practical applications, this concept is most useful for:',
            'options' => json_encode([
                ['id' => 0, 'type' => 'text', 'content' => 'Problem solving'],
                ['id' => 1, 'type' => 'text', 'content' => 'Making coffee'],
                ['id' => 2, 'type' => 'text', 'content' => 'Playing games'],
                ['id' => 3, 'type' => 'text', 'content' => 'Watching movies']
            ]),
            'correct_answer' => 0,
            'difficulty' => 'medium'
        ],
        [
            'type' => 'text',
            'content' => 'What is the correct sequence of steps in this process?',
            'options' => json_encode([
                ['id' => 0, 'type' => 'text', 'content' => 'Plan → Execute → Review'],
                ['id' => 1, 'type' => 'text', 'content' => 'Execute → Plan → Review'],
                ['id' => 2, 'type' => 'text', 'content' => 'Review → Execute → Plan'],
                ['id' => 3, 'type' => 'text', 'content' => 'Random order works fine']
            ]),
            'correct_answer' => 0,
            'difficulty' => 'high'
        ],
        [
            'type' => 'text',
            'content' => 'Which statement about this topic is FALSE?',
            'options' => json_encode([
                ['id' => 0, 'type' => 'text', 'content' => 'It requires systematic thinking'],
                ['id' => 1, 'type' => 'text', 'content' => 'It can be applied in real-world scenarios'],
                ['id' => 2, 'type' => 'text', 'content' => 'It has no practical value whatsoever'],
                ['id' => 3, 'type' => 'text', 'content' => 'It builds upon foundational knowledge']
            ]),
            'correct_answer' => 2,
            'difficulty' => 'medium'
        ],
        [
            'type' => 'html',
            'content' => '<div style="background:#f0f0f0;padding:10px;border-radius:5px;font-family:monospace;">x = 10<br>y = 20<br>result = x + y</div>',
            'question_text' => 'What is the value of result?',
            'options' => json_encode([
                ['id' => 0, 'type' => 'text', 'content' => '10'],
                ['id' => 1, 'type' => 'text', 'content' => '20'],
                ['id' => 2, 'type' => 'text', 'content' => '30'],
                ['id' => 3, 'type' => 'text', 'content' => '1020']
            ]),
            'correct_answer' => 2,
            'difficulty' => 'low'
        ],
        [
            'type' => 'text',
            'content' => 'Select the CORRECT definition:',
            'options' => json_encode([
                ['id' => 0, 'type' => 'text', 'content' => 'A method of organizing information logically'],
                ['id' => 1, 'type' => 'text', 'content' => 'A way to create chaos'],
                ['id' => 2, 'type' => 'text', 'content' => 'An outdated practice'],
                ['id' => 3, 'type' => 'text', 'content' => 'A random collection of ideas']
            ]),
            'correct_answer' => 0,
            'difficulty' => 'low'
        ],
        [
            'type' => 'text',
            'content' => 'What is the main advantage of understanding this concept?',
            'options' => json_encode([
                ['id' => 0, 'type' => 'text', 'content' => 'Better decision making'],
                ['id' => 1, 'type' => 'text', 'content' => 'No advantage at all'],
                ['id' => 2, 'type' => 'text', 'content' => 'It makes things more complicated'],
                ['id' => 3, 'type' => 'text', 'content' => 'It is only useful for exams']
            ]),
            'correct_answer' => 0,
            'difficulty' => 'medium'
        ],
        [
            'type' => 'text',
            'content' => 'How does this concept relate to the previous topics?',
            'options' => json_encode([
                ['id' => 0, 'type' => 'text', 'content' => 'It builds upon them'],
                ['id' => 1, 'type' => 'text', 'content' => 'It completely contradicts them'],
                ['id' => 2, 'type' => 'text', 'content' => 'There is no relationship'],
                ['id' => 3, 'type' => 'text', 'content' => 'It makes them obsolete']
            ]),
            'correct_answer' => 0,
            'difficulty' => 'high'
        ],
        [
            'type' => 'text',
            'content' => 'Which of the following is a prerequisite for mastering this topic?',
            'options' => json_encode([
                ['id' => 0, 'type' => 'text', 'content' => 'Basic understanding of fundamentals'],
                ['id' => 1, 'type' => 'text', 'content' => 'No prerequisites needed'],
                ['id' => 2, 'type' => 'text', 'content' => 'Advanced degree required'],
                ['id' => 3, 'type' => 'text', 'content' => 'Years of experience only']
            ]),
            'correct_answer' => 0,
            'difficulty' => 'low'
        ]
    ];
    
    $questionsAdded = 0;
    
    foreach ($concepts as $concept) {
        // Check existing questions
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM questions WHERE concept_id = ?");
        $stmt->execute([$concept['id']]);
        $existingCount = $stmt->fetch()['count'];
        
        // Add questions if less than 10
        $needed = 10 - $existingCount;
        if ($needed > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO questions (concept_id, type, content, question_text, options, correct_answer, difficulty, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, TRUE, NOW())
            ");
            
            for ($i = 0; $i < min($needed, count($sampleQuestions)); $i++) {
                $q = $sampleQuestions[$i];
                $questionText = $q['question_text'] ?? null;
                $stmt->execute([
                    $concept['id'],
                    $q['type'],
                    $q['content'],
                    $questionText,
                    $q['options'],
                    $q['correct_answer'],
                    $q['difficulty']
                ]);
                $questionsAdded++;
            }
        }
        
        echo "<p>✓ {$concept['course_name']} > Week {$concept['week_number']} > {$concept['lecture_title']} > {$concept['concept_title']}: " . ($existingCount + min($needed, count($sampleQuestions))) . " questions</p>";
    }
    
    echo "<div class='success'>Added {$questionsAdded} new questions!</div>";
    
    // =====================================================
    // STEP 3: Create quizzes for all concepts
    // =====================================================
    echo "<h2>Step 3: Creating Quizzes</h2>";
    
    // Quiz time window: now to 7 days from now
    $startTime = date('Y-m-d H:i:s');
    $endTime = date('Y-m-d H:i:s', strtotime('+7 days'));
    
    $quizzesCreated = 0;
    
    foreach ($concepts as $concept) {
        // Check if quiz already exists for this concept
        $stmt = $pdo->prepare("SELECT id FROM quizzes WHERE concept_id = ?");
        $stmt->execute([$concept['id']]);
        
        if (!$stmt->fetch()) {
            // Create quiz
            $stmt = $pdo->prepare("
                INSERT INTO quizzes (concept_id, title, num_questions, duration_minutes, start_time, end_time, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $quizTitle = "Quiz: " . $concept['concept_title'];
            $stmt->execute([
                $concept['id'],
                $quizTitle,
                5, // 5 questions per quiz
                10, // 10 minutes
                $startTime,
                $endTime,
                $facultyId
            ]);
            $quizzesCreated++;
            echo "<p>✓ Created quiz: {$quizTitle}</p>";
        } else {
            // Update existing quiz to be active now
            $stmt = $pdo->prepare("UPDATE quizzes SET start_time = ?, end_time = ? WHERE concept_id = ?");
            $stmt->execute([$startTime, $endTime, $concept['id']]);
            echo "<p>✓ Updated quiz for: {$concept['concept_title']} (now active)</p>";
        }
    }
    
    echo "<div class='success'>Created {$quizzesCreated} new quizzes!</div>";
    
    // =====================================================
    // SUMMARY
    // =====================================================
    echo "<h2>Summary</h2>";
    
    echo "<div class='info'>";
    echo "<p><strong>Student:</strong> student1@test.com is now enrolled in all courses</p>";
    echo "<p><strong>Quizzes:</strong> Available from now until " . date('M d, Y', strtotime('+7 days')) . "</p>";
    echo "<p><strong>Duration:</strong> 10 minutes per quiz</p>";
    echo "<p><strong>Questions:</strong> 5 questions per quiz (randomized from pool)</p>";
    echo "</div>";
    
    echo "<div class='warning'>";
    echo "<strong>Remember:</strong> Delete this file after use!<br>";
    echo "File: <code>" . __FILE__ . "</code>";
    echo "</div>";
    
    echo "<h2>Test Now</h2>";
    echo "<p><a href='public/login.html' style='display:inline-block;background:#4f46e5;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Go to Login</a></p>";
    echo "<p>Login as: <strong>student1@test.com</strong> / <strong>Admin@123</strong></p>";
    
} catch (Exception $e) {
    echo "<div style='background:#f8d7da;border:1px solid #f5c6cb;padding:15px;border-radius:5px;'>";
    echo "<strong>Error:</strong> " . $e->getMessage();
    echo "</div>";
}

echo "</body></html>";
?>
