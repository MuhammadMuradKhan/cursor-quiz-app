<?php
/**
 * Authentication API Endpoint
 * 
 * Handles: login, register, logout, password reset, change password
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modules/auth/functions.php';
require_once __DIR__ . '/../modules/auth/middleware.php';
require_once __DIR__ . '/../modules/email/functions.php';

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
    case 'login':
        handleLogin($input);
        break;
        
    case 'register':
        handleRegister($input);
        break;
        
    case 'logout':
        handleLogout();
        break;
        
    case 'me':
        handleGetCurrentUser();
        break;
        
    case 'forgot-password':
        handleForgotPassword($input);
        break;
        
    case 'reset-password':
        handleResetPassword($input);
        break;
        
    case 'change-password':
        handleChangePassword($input);
        break;
        
    default:
        jsonResponse(false, null, 'Invalid action. Available actions: login, register, logout, me, forgot-password, reset-password, change-password');
}

/**
 * Handle user login
 */
function handleLogin($input) {
    validateRequired($input, ['email', 'password']);
    
    $email = sanitize($input['email']);
    $password = $input['password']; // Don't sanitize password
    
    $result = loginUser($email, $password);
    
    jsonResponse($result['success'], $result['data'] ?? null, $result['message']);
}

/**
 * Handle user registration
 */
function handleRegister($input) {
    validateRequired($input, ['name', 'email', 'password']);
    
    $name = sanitize($input['name']);
    $email = sanitize($input['email']);
    $password = $input['password'];
    $role = 'student'; // Default to student for public registration
    $rollNo = isset($input['roll_no']) ? sanitize($input['roll_no']) : null;
    
    // Validate roll number for students
    if (empty($rollNo)) {
        jsonResponse(false, null, 'Roll number is required for student registration');
    }
    
    $result = registerUser($name, $email, $password, $role, $rollNo);
    
    if ($result['success']) {
        // Send registration email
        sendRegistrationEmail($email, $name);
    }
    
    jsonResponse($result['success'], $result['data'] ?? null, $result['message']);
}

/**
 * Handle user logout
 */
function handleLogout() {
    $result = logoutUser();
    jsonResponse($result['success'], null, $result['message']);
}

/**
 * Handle get current user
 */
function handleGetCurrentUser() {
    $user = getCurrentUser();
    
    if ($user) {
        jsonResponse(true, $user, 'User authenticated');
    } else {
        jsonResponse(false, null, 'Not authenticated');
    }
}

/**
 * Handle forgot password request
 */
function handleForgotPassword($input) {
    validateRequired($input, ['email']);
    
    $email = sanitize($input['email']);
    
    // Get user info
    $user = getUserByEmail($email);
    
    if (!$user) {
        // Don't reveal if email exists or not for security
        jsonResponse(true, null, 'If the email exists, a reset link has been sent');
        return;
    }
    
    $result = generatePasswordResetToken($email);
    
    if ($result['success']) {
        // Send reset email
        sendPasswordResetEmail($email, $user['name'], $result['data']['token']);
    }
    
    // Always return success to prevent email enumeration
    jsonResponse(true, null, 'If the email exists, a reset link has been sent');
}

/**
 * Handle password reset
 */
function handleResetPassword($input) {
    validateRequired($input, ['token', 'password']);
    
    $token = sanitize($input['token']);
    $password = $input['password'];
    
    $result = resetPassword($token, $password);
    
    jsonResponse($result['success'], null, $result['message']);
}

/**
 * Handle password change (when logged in)
 */
function handleChangePassword($input) {
    requireLogin();
    
    validateRequired($input, ['current_password', 'new_password']);
    
    $user = getCurrentUser();
    $currentPassword = $input['current_password'];
    $newPassword = $input['new_password'];
    
    $result = changePassword($user['id'], $currentPassword, $newPassword);
    
    jsonResponse($result['success'], null, $result['message']);
}
