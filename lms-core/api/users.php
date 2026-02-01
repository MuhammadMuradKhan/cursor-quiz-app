<?php
/**
 * Users API Endpoint
 * 
 * Handles: CRUD operations for users (admin only)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../modules/auth/functions.php';
require_once __DIR__ . '/../modules/auth/middleware.php';
require_once __DIR__ . '/../modules/users/functions.php';

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
    case 'list':
        handleListUsers($input);
        break;
        
    case 'get':
        handleGetUser($input);
        break;
        
    case 'create':
        handleCreateUser($input);
        break;
        
    case 'update':
        handleUpdateUser($input);
        break;
        
    case 'delete':
        handleDeleteUser($input);
        break;
        
    case 'restore':
        handleRestoreUser($input);
        break;
        
    default:
        jsonResponse(false, null, 'Invalid action. Available actions: list, get, create, update, delete, restore');
}

/**
 * List all users (with filters)
 */
function handleListUsers($input) {
    requireAdmin();
    
    $role = isset($input['role']) ? sanitize($input['role']) : null;
    $search = isset($input['search']) ? sanitize($input['search']) : null;
    $includeDeleted = isset($input['include_deleted']) && $input['include_deleted'] === 'true';
    
    $result = getAllUsers($role, $search, $includeDeleted);
    
    jsonResponse(true, $result, 'Users retrieved successfully');
}

/**
 * Get single user
 */
function handleGetUser($input) {
    requireAdmin();
    
    validateRequired($input, ['id']);
    
    $userId = (int) $input['id'];
    $user = getUserById($userId);
    
    if ($user) {
        jsonResponse(true, $user, 'User retrieved successfully');
    } else {
        jsonResponse(false, null, 'User not found');
    }
}

/**
 * Create new user (faculty by admin)
 */
function handleCreateUser($input) {
    requireAdmin();
    
    validateRequired($input, ['name', 'email', 'password', 'role']);
    
    $name = sanitize($input['name']);
    $email = sanitize($input['email']);
    $password = $input['password'];
    $role = sanitize($input['role']);
    $rollNo = isset($input['roll_no']) ? sanitize($input['roll_no']) : null;
    
    // Only allow creating faculty through admin
    if (!in_array($role, ['faculty', 'admin', 'student'])) {
        jsonResponse(false, null, 'Invalid role');
    }
    
    $result = registerUser($name, $email, $password, $role, $rollNo);
    
    jsonResponse($result['success'], $result['data'] ?? null, $result['message']);
}

/**
 * Update user
 */
function handleUpdateUser($input) {
    requireAdmin();
    
    validateRequired($input, ['id']);
    
    $userId = (int) $input['id'];
    $updates = [];
    
    if (isset($input['name'])) $updates['name'] = sanitize($input['name']);
    if (isset($input['email'])) $updates['email'] = sanitize($input['email']);
    if (isset($input['role'])) $updates['role'] = sanitize($input['role']);
    if (isset($input['roll_no'])) $updates['roll_no'] = sanitize($input['roll_no']);
    
    if (empty($updates)) {
        jsonResponse(false, null, 'No updates provided');
    }
    
    $result = updateUser($userId, $updates);
    
    jsonResponse($result['success'], null, $result['message']);
}

/**
 * Delete user (soft delete)
 */
function handleDeleteUser($input) {
    requireAdmin();
    
    validateRequired($input, ['id']);
    
    $userId = (int) $input['id'];
    
    // Prevent deleting yourself
    $currentUser = getCurrentUser();
    if ($currentUser['id'] == $userId) {
        jsonResponse(false, null, 'Cannot delete your own account');
    }
    
    $result = deleteUser($userId);
    
    jsonResponse($result['success'], null, $result['message']);
}

/**
 * Restore deleted user
 */
function handleRestoreUser($input) {
    requireAdmin();
    
    validateRequired($input, ['id']);
    
    $userId = (int) $input['id'];
    $result = restoreUser($userId);
    
    jsonResponse($result['success'], null, $result['message']);
}
