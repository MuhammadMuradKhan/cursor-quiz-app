<?php
/**
 * Authentication Functions
 */

require_once __DIR__ . '/../../config.php';

/**
 * Register a new user
 */
function registerUser($name, $email, $password, $role = 'student', $rollNo = null) {
    $pdo = getDBConnection();
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email format'];
    }
    
    // Validate password strength
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'];
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Email already registered'];
    }
    
    // Check if roll number already exists (for students)
    if ($role === 'student' && $rollNo) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE roll_no = ? AND deleted_at IS NULL");
        $stmt->execute([$rollNo]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Roll number already registered'];
        }
    }
    
    // Validate role
    if (!in_array($role, ['admin', 'faculty', 'student'])) {
        return ['success' => false, 'message' => 'Invalid role'];
    }
    
    // Hash password
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    
    // Insert user
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password_hash, role, roll_no, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    try {
        $stmt->execute([$name, $email, $passwordHash, $role, $rollNo]);
        $userId = $pdo->lastInsertId();
        
        return [
            'success' => true,
            'message' => 'Registration successful',
            'data' => ['user_id' => $userId]
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
    }
}

/**
 * Login user
 */
function loginUser($email, $password) {
    $pdo = getDBConnection();
    
    // Get user
    $stmt = $pdo->prepare("
        SELECT id, name, email, password_hash, role, roll_no 
        FROM users 
        WHERE email = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    // Start session
    initSession();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_roll_no'] = $user['roll_no'];
    $_SESSION['login_time'] = time();
    
    return [
        'success' => true,
        'message' => 'Login successful',
        'data' => [
            'user_id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'roll_no' => $user['roll_no']
        ]
    ];
}

/**
 * Logout user
 */
function logoutUser() {
    initSession();
    session_unset();
    session_destroy();
    
    return ['success' => true, 'message' => 'Logged out successfully'];
}

/**
 * Get current logged in user
 */
function getCurrentUser() {
    initSession();
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    // Check session expiry
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_LIFETIME) {
        logoutUser();
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role' => $_SESSION['user_role'],
        'roll_no' => $_SESSION['user_roll_no']
    ];
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return getCurrentUser() !== null;
}

/**
 * Check if user has specific role
 */
function hasRole($role) {
    $user = getCurrentUser();
    if (!$user) return false;
    
    if (is_array($role)) {
        return in_array($user['role'], $role);
    }
    
    return $user['role'] === $role;
}

/**
 * Generate password reset token
 */
function generatePasswordResetToken($email) {
    $pdo = getDBConnection();
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL");
    $stmt->execute([$email]);
    if (!$stmt->fetch()) {
        return ['success' => false, 'message' => 'Email not found'];
    }
    
    // Generate token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + PASSWORD_RESET_EXPIRY);
    
    // Invalidate any existing tokens
    $stmt = $pdo->prepare("UPDATE password_resets SET used = TRUE WHERE email = ?");
    $stmt->execute([$email]);
    
    // Insert new token
    $stmt = $pdo->prepare("
        INSERT INTO password_resets (email, token, expires_at, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$email, $token, $expiresAt]);
    
    return [
        'success' => true,
        'message' => 'Reset token generated',
        'data' => ['token' => $token, 'expires_at' => $expiresAt]
    ];
}

/**
 * Reset password using token
 */
function resetPassword($token, $newPassword) {
    $pdo = getDBConnection();
    
    // Validate password strength
    if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
        return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'];
    }
    
    // Find valid token
    $stmt = $pdo->prepare("
        SELECT email FROM password_resets 
        WHERE token = ? AND used = FALSE AND expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    
    if (!$reset) {
        return ['success' => false, 'message' => 'Invalid or expired reset token'];
    }
    
    // Update password
    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE email = ?");
    $stmt->execute([$passwordHash, $reset['email']]);
    
    // Mark token as used
    $stmt = $pdo->prepare("UPDATE password_resets SET used = TRUE WHERE token = ?");
    $stmt->execute([$token]);
    
    return ['success' => true, 'message' => 'Password reset successful'];
}

/**
 * Change password (when logged in)
 */
function changePassword($userId, $currentPassword, $newPassword) {
    $pdo = getDBConnection();
    
    // Validate new password strength
    if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
        return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'];
    }
    
    // Get current password hash
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    // Verify current password
    if (!password_verify($currentPassword, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Current password is incorrect'];
    }
    
    // Update password
    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$passwordHash, $userId]);
    
    return ['success' => true, 'message' => 'Password changed successfully'];
}

/**
 * Log login attempt
 */
function logLoginAttempt($email, $ip, $success) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        INSERT INTO login_attempts (email, ip_address, success, attempted_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$email, $ip, $success ? 1 : 0]);
}

/**
 * Get recent failed login attempts
 */
function getRecentFailedAttempts($email) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM login_attempts 
        WHERE email = ? AND success = FALSE 
        AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    $stmt->execute([$email, LOGIN_BLOCK_DURATION]);
    $result = $stmt->fetch();
    return (int) $result['count'];
}

/**
 * Block user temporarily
 */
function blockUser($email) {
    $pdo = getDBConnection();
    $blockedUntil = date('Y-m-d H:i:s', time() + LOGIN_BLOCK_DURATION);
    $stmt = $pdo->prepare("
        UPDATE users SET is_blocked = TRUE, blocked_until = ? 
        WHERE email = ?
    ");
    $stmt->execute([$blockedUntil, $email]);
}

/**
 * Clear user block
 */
function clearUserBlock($email) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        UPDATE users SET is_blocked = FALSE, blocked_until = NULL 
        WHERE email = ?
    ");
    $stmt->execute([$email]);
}

/**
 * Check if login is blocked
 */
function checkLoginBlock($email) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT is_blocked, blocked_until FROM users 
        WHERE email = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        return ['blocked' => false];
    }
    
    if ($user['is_blocked'] && $user['blocked_until']) {
        $blockedUntil = strtotime($user['blocked_until']);
        if ($blockedUntil > time()) {
            $minutesRemaining = ceil(($blockedUntil - time()) / 60);
            return ['blocked' => true, 'minutes_remaining' => $minutesRemaining];
        } else {
            // Block expired, clear it
            clearUserBlock($email);
        }
    }
    
    return ['blocked' => false];
}

/**
 * Get user by ID
 */
function getUserById($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT id, name, email, role, roll_no, created_at 
        FROM users 
        WHERE id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

/**
 * Get user by email
 */
function getUserByEmail($email) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT id, name, email, role, roll_no, created_at 
        FROM users 
        WHERE email = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$email]);
    return $stmt->fetch();
}
