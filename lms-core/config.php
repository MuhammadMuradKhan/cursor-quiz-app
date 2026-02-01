<?php
/**
 * LMS Configuration File
 */

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'lms_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// SMTP Configuration
define('SMTP_HOST', 'murad.phd');
define('SMTP_PORT', 465);
define('SMTP_USER', 'ali@murad.phd');
define('SMTP_PASS', 'm01jMsGNt!lg');
define('SMTP_FROM', 'ali@murad.phd');
define('SMTP_FROM_NAME', 'LMS System');

// API Configuration
define('MASTER_API_KEY', 'lms-master-key-2024-secure');

// Application Settings
define('APP_NAME', 'LMS');
define('APP_URL', 'http://localhost/lms-core');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_MAX_SIZE', 25 * 1024 * 1024); // 25MB

// Session Settings
define('SESSION_LIFETIME', 7 * 24 * 60 * 60); // 1 week in seconds

// Security Settings
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_BLOCK_DURATION', 30 * 60); // 30 minutes in seconds
define('PASSWORD_RESET_EXPIRY', 60 * 60); // 1 hour in seconds
define('PASSWORD_MIN_LENGTH', 8);

// Quiz Settings
define('QUIZ_DEFAULT_DURATION', 10); // 10 minutes
define('QUIZ_TAB_SWITCH_GRACE', 5); // 5 seconds before violation

// Timezone
date_default_timezone_set('Asia/Karachi');

// Database Connection Function
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode([
                'success' => false,
                'message' => 'Database connection failed: ' . $e->getMessage()
            ]));
        }
    }
    
    return $pdo;
}

// Start session with custom settings
function initSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_lifetime', SESSION_LIFETIME);
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
        session_start();
    }
}

// JSON Response Helper
function jsonResponse($success, $data = null, $message = '') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ]);
    exit;
}

// CORS Headers (for API access)
function setCORSHeaders() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}
