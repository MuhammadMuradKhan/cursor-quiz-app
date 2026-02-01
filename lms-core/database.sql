-- LMS Database Schema
-- Run this file in phpMyAdmin to create all tables

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS lms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lms_db;

-- =====================================================
-- USERS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'faculty', 'student') NOT NULL DEFAULT 'student',
    roll_no VARCHAR(50) NULL UNIQUE,
    is_blocked BOOLEAN DEFAULT FALSE,
    blocked_until DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_roll_no (roll_no)
) ENGINE=InnoDB;

-- =====================================================
-- COURSES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NOT NULL,
    description TEXT NULL,
    thumbnail VARCHAR(255) NULL,
    header_image VARCHAR(255) NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    invite_code VARCHAR(50) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (faculty_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_faculty (faculty_id),
    INDEX idx_invite_code (invite_code)
) ENGINE=InnoDB;

-- =====================================================
-- WEEKS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS weeks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    week_number INT NOT NULL,
    title VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_course (course_id),
    UNIQUE KEY unique_week (course_id, week_number)
) ENGINE=InnoDB;

-- =====================================================
-- LECTURES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS lectures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    week_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    order_index INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (week_id) REFERENCES weeks(id) ON DELETE CASCADE,
    INDEX idx_week (week_id)
) ENGINE=InnoDB;

-- =====================================================
-- CONCEPTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS concepts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lecture_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    order_index INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lecture_id) REFERENCES lectures(id) ON DELETE CASCADE,
    INDEX idx_lecture (lecture_id)
) ENGINE=InnoDB;

-- =====================================================
-- MATERIALS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    concept_id INT NOT NULL,
    type ENUM('pdf', 'video', 'html', 'ppt', 'zip', 'other') NOT NULL,
    title VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NULL,
    content TEXT NULL,
    external_url VARCHAR(500) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (concept_id) REFERENCES concepts(id) ON DELETE CASCADE,
    INDEX idx_concept (concept_id)
) ENGINE=InnoDB;

-- =====================================================
-- ENROLLMENTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    student_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (course_id, student_id),
    INDEX idx_course (course_id),
    INDEX idx_student (student_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- =====================================================
-- QUESTIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    concept_id INT NOT NULL,
    type ENUM('text', 'image', 'html', 'video') NOT NULL DEFAULT 'text',
    content TEXT NOT NULL,
    question_text TEXT NULL,
    options JSON NOT NULL,
    correct_answer INT NOT NULL,
    difficulty ENUM('low', 'medium', 'high') DEFAULT 'medium',
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (concept_id) REFERENCES concepts(id) ON DELETE CASCADE,
    INDEX idx_concept (concept_id),
    INDEX idx_difficulty (difficulty),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- =====================================================
-- QUIZZES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    concept_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    num_questions INT NOT NULL DEFAULT 10,
    duration_minutes INT NOT NULL DEFAULT 10,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (concept_id) REFERENCES concepts(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_concept (concept_id),
    INDEX idx_time (start_time, end_time)
) ENGINE=InnoDB;

-- =====================================================
-- QUIZ ATTEMPTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS quiz_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    student_id INT NOT NULL,
    question_ids JSON NOT NULL,
    answers JSON NULL,
    score INT DEFAULT 0,
    max_score INT NOT NULL,
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    submitted_at DATETIME NULL,
    is_violation BOOLEAN DEFAULT FALSE,
    violation_reason VARCHAR(255) NULL,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attempt (quiz_id, student_id),
    INDEX idx_quiz (quiz_id),
    INDEX idx_student (student_id),
    INDEX idx_violation (is_violation)
) ENGINE=InnoDB;

-- =====================================================
-- FEEDBACK TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    feedback_type ENUM('course', 'concept', 'quiz', 'faculty', 'system', 'general') NOT NULL,
    target_id INT NULL,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_type (feedback_type)
) ENGINE=InnoDB;

-- =====================================================
-- PASSWORD RESETS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    INDEX idx_email (email),
    INDEX idx_token (token)
) ENGINE=InnoDB;

-- =====================================================
-- LOGIN ATTEMPTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    success BOOLEAN DEFAULT FALSE,
    attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_ip (ip_address),
    INDEX idx_time (attempted_at)
) ENGINE=InnoDB;

-- =====================================================
-- API KEYS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_value VARCHAR(255) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_key (key_value)
) ENGINE=InnoDB;

-- =====================================================
-- INSERT DEFAULT DATA
-- =====================================================

-- Insert master API key
INSERT INTO api_keys (key_value, description, is_active) 
VALUES ('lms-master-key-2024-secure', 'Master API Key for external question insertion', TRUE)
ON DUPLICATE KEY UPDATE description = description;

-- Insert default admin user (password: Admin@123)
INSERT INTO users (name, email, password_hash, role) 
VALUES ('System Admin', 'admin@murad.phd', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin')
ON DUPLICATE KEY UPDATE name = name;

-- =====================================================
-- DUMMY DATA FOR TESTING
-- =====================================================

-- Faculty users (password: Faculty@123 for all)
INSERT INTO users (name, email, password_hash, role) VALUES
('Dr. Ali Murad', 'ali@murad.phd', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'faculty'),
('Dr. Sarah Khan', 'sarah@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'faculty'),
('Dr. Ahmed Hassan', 'ahmed@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'faculty')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Student users (password: Student@123 for all)
INSERT INTO users (name, email, password_hash, role, roll_no) VALUES
('Muhammad Ali', 'student1@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2024-CS-001'),
('Fatima Ahmed', 'student2@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2024-CS-002'),
('Hassan Malik', 'student3@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2024-CS-003'),
('Ayesha Khan', 'student4@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2024-CS-004'),
('Omar Farooq', 'student5@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2024-CS-005'),
('Zainab Hussain', 'student6@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2024-CS-006'),
('Bilal Ahmad', 'student7@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2024-CS-007'),
('Maryam Siddiqui', 'student8@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2024-CS-008'),
('Usman Ali', 'student9@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2024-CS-009'),
('Hira Nawaz', 'student10@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2024-CS-010'),
('Kamran Shah', 'student11@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2024-CS-011'),
('Sana Rafiq', 'student12@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2024-CS-012'),
('Faisal Iqbal', 'student13@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2024-CS-013'),
('Nadia Jamil', 'student14@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2024-CS-014'),
('Tariq Mehmood', 'student15@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2024-CS-015'),
('Rabia Noor', 'student16@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2024-CS-016'),
('Imran Qureshi', 'student17@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2024-CS-017'),
('Samina Bibi', 'student18@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2024-CS-018'),
('Waqar Ahmed', 'student19@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2024-CS-019'),
('Lubna Sheikh', 'student20@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', '2024-CS-020')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Sample Courses
INSERT INTO courses (faculty_id, name, code, description, invite_code, start_date, end_date) VALUES
((SELECT id FROM users WHERE email = 'ali@murad.phd'), 'Introduction to Programming', 'CS101', 'Learn the fundamentals of programming using Python. This course covers variables, data types, control structures, functions, and basic algorithms.', 'CS101-2024-INVITE', '2024-01-15', '2024-05-15'),
((SELECT id FROM users WHERE email = 'sarah@university.edu'), 'Data Structures', 'CS201', 'Advanced course covering arrays, linked lists, stacks, queues, trees, and graphs. Includes algorithm analysis and complexity.', 'CS201-2024-INVITE', '2024-01-15', '2024-05-15'),
((SELECT id FROM users WHERE email = 'ahmed@university.edu'), 'Database Systems', 'CS301', 'Introduction to database design, SQL, normalization, transactions, and database management systems.', 'CS301-2024-INVITE', '2024-01-15', '2024-05-15')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Sample Weeks for CS101
INSERT INTO weeks (course_id, week_number, title) VALUES
((SELECT id FROM courses WHERE code = 'CS101'), 1, 'Introduction to Programming'),
((SELECT id FROM courses WHERE code = 'CS101'), 2, 'Variables and Data Types'),
((SELECT id FROM courses WHERE code = 'CS101'), 3, 'Operators and Expressions'),
((SELECT id FROM courses WHERE code = 'CS101'), 4, 'Control Structures - If/Else')
ON DUPLICATE KEY UPDATE title = VALUES(title);

-- Sample Lectures for Week 1
INSERT INTO lectures (week_id, title, order_index) VALUES
((SELECT id FROM weeks WHERE course_id = (SELECT id FROM courses WHERE code = 'CS101') AND week_number = 1), 'What is Programming?', 1),
((SELECT id FROM weeks WHERE course_id = (SELECT id FROM courses WHERE code = 'CS101') AND week_number = 1), 'Setting Up Python', 2)
ON DUPLICATE KEY UPDATE title = VALUES(title);

-- Sample Lectures for Week 2
INSERT INTO lectures (week_id, title, order_index) VALUES
((SELECT id FROM weeks WHERE course_id = (SELECT id FROM courses WHERE code = 'CS101') AND week_number = 2), 'Variables in Python', 1),
((SELECT id FROM weeks WHERE course_id = (SELECT id FROM courses WHERE code = 'CS101') AND week_number = 2), 'Data Types Overview', 2)
ON DUPLICATE KEY UPDATE title = VALUES(title);

-- Sample Concepts for Lecture 1 (What is Programming?)
INSERT INTO concepts (lecture_id, title, order_index) VALUES
((SELECT l.id FROM lectures l JOIN weeks w ON l.week_id = w.id JOIN courses c ON w.course_id = c.id WHERE c.code = 'CS101' AND w.week_number = 1 AND l.order_index = 1 LIMIT 1), 'Definition of Programming', 1),
((SELECT l.id FROM lectures l JOIN weeks w ON l.week_id = w.id JOIN courses c ON w.course_id = c.id WHERE c.code = 'CS101' AND w.week_number = 1 AND l.order_index = 1 LIMIT 1), 'Programming Languages Overview', 2),
((SELECT l.id FROM lectures l JOIN weeks w ON l.week_id = w.id JOIN courses c ON w.course_id = c.id WHERE c.code = 'CS101' AND w.week_number = 1 AND l.order_index = 1 LIMIT 1), 'Why Learn Programming', 3)
ON DUPLICATE KEY UPDATE title = VALUES(title);

-- Sample Concepts for Lecture 2 (Variables in Python)
INSERT INTO concepts (lecture_id, title, order_index) VALUES
((SELECT l.id FROM lectures l JOIN weeks w ON l.week_id = w.id JOIN courses c ON w.course_id = c.id WHERE c.code = 'CS101' AND w.week_number = 2 AND l.order_index = 1 LIMIT 1), 'What are Variables', 1),
((SELECT l.id FROM lectures l JOIN weeks w ON l.week_id = w.id JOIN courses c ON w.course_id = c.id WHERE c.code = 'CS101' AND w.week_number = 2 AND l.order_index = 1 LIMIT 1), 'Variable Naming Rules', 2),
((SELECT l.id FROM lectures l JOIN weeks w ON l.week_id = w.id JOIN courses c ON w.course_id = c.id WHERE c.code = 'CS101' AND w.week_number = 2 AND l.order_index = 1 LIMIT 1), 'Variable Assignment', 3)
ON DUPLICATE KEY UPDATE title = VALUES(title);

-- Sample Questions for "What are Variables" concept
INSERT INTO questions (concept_id, type, content, options, correct_answer, difficulty, is_active) VALUES
((SELECT c.id FROM concepts c JOIN lectures l ON c.lecture_id = l.id JOIN weeks w ON l.week_id = w.id JOIN courses co ON w.course_id = co.id WHERE co.code = 'CS101' AND c.title = 'What are Variables' LIMIT 1),
'text', 'What is a variable in programming?', 
'[{"id": 0, "type": "text", "content": "A fixed value that cannot change"}, {"id": 1, "type": "text", "content": "A container that stores data values"}, {"id": 2, "type": "text", "content": "A type of loop"}, {"id": 3, "type": "text", "content": "A function name"}]',
1, 'low', TRUE),

((SELECT c.id FROM concepts c JOIN lectures l ON c.lecture_id = l.id JOIN weeks w ON l.week_id = w.id JOIN courses co ON w.course_id = co.id WHERE co.code = 'CS101' AND c.title = 'What are Variables' LIMIT 1),
'text', 'Which of the following is a valid variable name in Python?',
'[{"id": 0, "type": "text", "content": "2myvar"}, {"id": 1, "type": "text", "content": "my-var"}, {"id": 2, "type": "text", "content": "my_var"}, {"id": 3, "type": "text", "content": "my var"}]',
2, 'medium', TRUE),

((SELECT c.id FROM concepts c JOIN lectures l ON c.lecture_id = l.id JOIN weeks w ON l.week_id = w.id JOIN courses co ON w.course_id = co.id WHERE co.code = 'CS101' AND c.title = 'What are Variables' LIMIT 1),
'text', 'What symbol is used for variable assignment in Python?',
'[{"id": 0, "type": "text", "content": "=="}, {"id": 1, "type": "text", "content": "="}, {"id": 2, "type": "text", "content": ":="}, {"id": 3, "type": "text", "content": "=>"}]',
1, 'low', TRUE),

((SELECT c.id FROM concepts c JOIN lectures l ON c.lecture_id = l.id JOIN weeks w ON l.week_id = w.id JOIN courses co ON w.course_id = co.id WHERE co.code = 'CS101' AND c.title = 'What are Variables' LIMIT 1),
'text', 'Can a variable name start with a number in Python?',
'[{"id": 0, "type": "text", "content": "Yes, always"}, {"id": 1, "type": "text", "content": "No, never"}, {"id": 2, "type": "text", "content": "Only if it ends with a letter"}, {"id": 3, "type": "text", "content": "Only in Python 3"}]',
1, 'medium', TRUE),

((SELECT c.id FROM concepts c JOIN lectures l ON c.lecture_id = l.id JOIN weeks w ON l.week_id = w.id JOIN courses co ON w.course_id = co.id WHERE co.code = 'CS101' AND c.title = 'What are Variables' LIMIT 1),
'text', 'Which keyword is used to delete a variable in Python?',
'[{"id": 0, "type": "text", "content": "remove"}, {"id": 1, "type": "text", "content": "delete"}, {"id": 2, "type": "text", "content": "del"}, {"id": 3, "type": "text", "content": "clear"}]',
2, 'high', TRUE)
ON DUPLICATE KEY UPDATE content = VALUES(content);

-- Sample Questions for "Definition of Programming" concept
INSERT INTO questions (concept_id, type, content, options, correct_answer, difficulty, is_active) VALUES
((SELECT c.id FROM concepts c JOIN lectures l ON c.lecture_id = l.id JOIN weeks w ON l.week_id = w.id JOIN courses co ON w.course_id = co.id WHERE co.code = 'CS101' AND c.title = 'Definition of Programming' LIMIT 1),
'text', 'What is the primary purpose of programming?',
'[{"id": 0, "type": "text", "content": "To create visual designs"}, {"id": 1, "type": "text", "content": "To give instructions to computers"}, {"id": 2, "type": "text", "content": "To repair hardware"}, {"id": 3, "type": "text", "content": "To browse the internet"}]',
1, 'low', TRUE),

((SELECT c.id FROM concepts c JOIN lectures l ON c.lecture_id = l.id JOIN weeks w ON l.week_id = w.id JOIN courses co ON w.course_id = co.id WHERE co.code = 'CS101' AND c.title = 'Definition of Programming' LIMIT 1),
'text', 'What is source code?',
'[{"id": 0, "type": "text", "content": "The compiled program"}, {"id": 1, "type": "text", "content": "Human-readable instructions written by programmers"}, {"id": 2, "type": "text", "content": "The computer hardware"}, {"id": 3, "type": "text", "content": "An operating system"}]',
1, 'medium', TRUE),

((SELECT c.id FROM concepts c JOIN lectures l ON c.lecture_id = l.id JOIN weeks w ON l.week_id = w.id JOIN courses co ON w.course_id = co.id WHERE co.code = 'CS101' AND c.title = 'Definition of Programming' LIMIT 1),
'text', 'Which of these is NOT a programming language?',
'[{"id": 0, "type": "text", "content": "Python"}, {"id": 1, "type": "text", "content": "HTML"}, {"id": 2, "type": "text", "content": "Java"}, {"id": 3, "type": "text", "content": "Microsoft Word"}]',
3, 'low', TRUE),

((SELECT c.id FROM concepts c JOIN lectures l ON c.lecture_id = l.id JOIN weeks w ON l.week_id = w.id JOIN courses co ON w.course_id = co.id WHERE co.code = 'CS101' AND c.title = 'Definition of Programming' LIMIT 1),
'text', 'What does a compiler do?',
'[{"id": 0, "type": "text", "content": "Writes code automatically"}, {"id": 1, "type": "text", "content": "Translates source code to machine code"}, {"id": 2, "type": "text", "content": "Finds bugs in code"}, {"id": 3, "type": "text", "content": "Connects to the internet"}]',
1, 'medium', TRUE),

((SELECT c.id FROM concepts c JOIN lectures l ON c.lecture_id = l.id JOIN weeks w ON l.week_id = w.id JOIN courses co ON w.course_id = co.id WHERE co.code = 'CS101' AND c.title = 'Definition of Programming' LIMIT 1),
'text', 'An algorithm is best described as:',
'[{"id": 0, "type": "text", "content": "A programming language"}, {"id": 1, "type": "text", "content": "A step-by-step procedure to solve a problem"}, {"id": 2, "type": "text", "content": "A type of computer"}, {"id": 3, "type": "text", "content": "A software application"}]',
1, 'medium', TRUE)
ON DUPLICATE KEY UPDATE content = VALUES(content);

-- Sample Enrollments (some approved, some pending)
INSERT INTO enrollments (course_id, student_id, status) VALUES
((SELECT id FROM courses WHERE code = 'CS101'), (SELECT id FROM users WHERE email = 'student1@test.com'), 'approved'),
((SELECT id FROM courses WHERE code = 'CS101'), (SELECT id FROM users WHERE email = 'student2@test.com'), 'approved'),
((SELECT id FROM courses WHERE code = 'CS101'), (SELECT id FROM users WHERE email = 'student3@test.com'), 'approved'),
((SELECT id FROM courses WHERE code = 'CS101'), (SELECT id FROM users WHERE email = 'student4@test.com'), 'approved'),
((SELECT id FROM courses WHERE code = 'CS101'), (SELECT id FROM users WHERE email = 'student5@test.com'), 'pending'),
((SELECT id FROM courses WHERE code = 'CS101'), (SELECT id FROM users WHERE email = 'student6@test.com'), 'pending'),
((SELECT id FROM courses WHERE code = 'CS201'), (SELECT id FROM users WHERE email = 'student1@test.com'), 'approved'),
((SELECT id FROM courses WHERE code = 'CS201'), (SELECT id FROM users WHERE email = 'student7@test.com'), 'approved'),
((SELECT id FROM courses WHERE code = 'CS201'), (SELECT id FROM users WHERE email = 'student8@test.com'), 'pending')
ON DUPLICATE KEY UPDATE status = VALUES(status);
